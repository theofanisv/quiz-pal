<?php

namespace App\Actions\Questions;

use App\Enums\QuestionSource;
use App\Models\Category;
use App\Models\Question;
use App\Support\OpenTdbClient;
use App\Support\RemovedOpenTdbQuestions;
use Closure;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Developer tool: pull the multiple-choice questions of Open Trivia DB into the local
 * question bank, one category per OpenTDB category, tagged with the "opentdb" source, so
 * that `quiz:export-opentdb` can write the committed snapshot. The rows it writes are
 * English only, so a run clears local Greek text; the exporter restores the translations
 * from the committed files. Questions on the removed list (`RemovedOpenTdbQuestions`) are
 * skipped like unusable ones.
 *
 * OpenTDB serves one category per request, at most 50 questions per page, and one request
 * per 5 seconds per IP. With a session token it answers code 4 as soon as the requested
 * amount exceeds what the category has left, so the page size is halved until it reaches
 * zero: that is how a category is known to be exhausted.
 *
 * @phpstan-import-type OpenTdbCategory from OpenTdbClient
 * @phpstan-import-type OpenTdbQuestion from OpenTdbClient
 * @phpstan-import-type OpenTdbPage from OpenTdbClient
 */
class SyncOpenTdbQuestions
{
    public const int SECONDS_BETWEEN_REQUESTS = 5;

    private const int MAX_RATE_LIMIT_ATTEMPTS = 3;

    private const int MAX_QUESTION_LENGTH = 500;

    /** @var array<string, int> */
    private const array DIFFICULTIES = ['easy' => 1, 'medium' => 2, 'hard' => 3];

    /** @var array<int, string> Greek names for the OpenTDB categories, keyed by OpenTDB id. */
    private const array GREEK_NAMES = [
        9 => 'Γενικές γνώσεις',
        10 => 'Ψυχαγωγία: Βιβλία',
        11 => 'Ψυχαγωγία: Κινηματογράφος',
        12 => 'Ψυχαγωγία: Μουσική',
        13 => 'Ψυχαγωγία: Μιούζικαλ & Θέατρο',
        14 => 'Ψυχαγωγία: Τηλεόραση',
        15 => 'Ψυχαγωγία: Βιντεοπαιχνίδια',
        16 => 'Ψυχαγωγία: Επιτραπέζια',
        17 => 'Επιστήμη & Φύση',
        18 => 'Επιστήμη: Υπολογιστές',
        19 => 'Επιστήμη: Μαθηματικά',
        20 => 'Μυθολογία',
        21 => 'Αθλητισμός',
        22 => 'Γεωγραφία',
        23 => 'Ιστορία',
        24 => 'Πολιτική',
        25 => 'Τέχνη',
        26 => 'Διασημότητες',
        27 => 'Ζώα',
        28 => 'Οχήματα',
        29 => 'Ψυχαγωγία: Κόμικς',
        30 => 'Επιστήμη: Gadgets',
        31 => 'Ψυχαγωγία: Άνιμε & Μάνγκα',
        32 => 'Ψυχαγωγία: Κινούμενα σχέδια',
    ];

    private int $requests = 0;

    public function __construct(private OpenTdbClient $client, private RemovedOpenTdbQuestions $removed) {}

    /**
     * @param  list<int>|null  $onlyExternalIds  Restrict the run to these OpenTDB category ids.
     * @return array{categories: int, questions: int, skipped: int}
     *
     * @throws RuntimeException when OpenTDB answers with an error code
     * @throws HttpClientException when OpenTDB cannot be reached or answers with an HTTP error
     */
    public function handle(?array $onlyExternalIds = null): array
    {
        $this->requests = 0;

        $token = $this->paced(fn (): string => $this->client->requestToken());
        $remoteCategories = $this->paced(fn (): array => $this->client->categories());

        if ($onlyExternalIds !== null) {
            $remoteCategories = array_values(array_filter(
                $remoteCategories,
                fn (array $remote): bool => in_array($remote['id'], $onlyExternalIds, true),
            ));
        }

        $questions = 0;
        $skipped = 0;

        foreach ($remoteCategories as $remote) {
            $category = $this->syncCategory($remote);
            [$imported, $rejected] = $this->syncQuestions($category, $token);

            $questions += $imported;
            $skipped += $rejected;
        }

        return ['categories' => count($remoteCategories), 'questions' => $questions, 'skipped' => $skipped];
    }

    /**
     * @param  OpenTdbCategory  $remote
     */
    private function syncCategory(array $remote): Category
    {
        return Category::query()->updateOrCreate(
            ['slug' => "opentdb-{$remote['id']}"],
            [
                'source' => QuestionSource::OpenTdb,
                'external_id' => $remote['id'],
                'name_en' => $remote['name'],
                'name_el' => self::GREEK_NAMES[$remote['id']] ?? $remote['name'],
                'sort_order' => $remote['id'],
            ],
        );
    }

    /**
     * Page through the category until OpenTDB has nothing left for this token.
     *
     * @return array{0: int, 1: int} imported and skipped counts
     */
    private function syncQuestions(Category $category, string $token): array
    {
        $amount = OpenTdbClient::MAX_AMOUNT;
        $imported = 0;
        $skipped = 0;

        while ($amount > 0) {
            $page = $this->fetchPage($category, $amount, $token);

            if ($page['code'] === OpenTdbClient::CODE_SUCCESS) {
                [$stored, $rejected] = $this->storePage($category, $page['results']);
                $imported += $stored;
                $skipped += $rejected;

                continue;
            }

            if (in_array($page['code'], [OpenTdbClient::CODE_NO_RESULTS, OpenTdbClient::CODE_TOKEN_EMPTY], true)) {
                $amount = intdiv($amount, 2);

                continue;
            }

            throw new RuntimeException("Open Trivia DB answered with code {$page['code']} for {$category->name_en}.");
        }

        return [$imported, $skipped];
    }

    /**
     * @return OpenTdbPage
     */
    private function fetchPage(Category $category, int $amount, string $token): array
    {
        $externalId = (int) $category->external_id;

        for ($attempt = 1; ; $attempt++) {
            $page = $this->paced(fn (): array => $this->client->questions($externalId, $amount, $token));

            if ($page['code'] !== OpenTdbClient::CODE_RATE_LIMITED || $attempt >= self::MAX_RATE_LIMIT_ATTEMPTS) {
                return $page;
            }
        }
    }

    /**
     * @param  list<OpenTdbQuestion>  $results
     * @return array{0: int, 1: int} stored and skipped counts
     */
    private function storePage(Category $category, array $results): array
    {
        return DB::transaction(function () use ($category, $results): array {
            $stored = 0;
            $skipped = 0;

            foreach ($results as $result) {
                $answers = [$result['correct_answer'], ...$result['incorrect_answers']];

                if (! $this->isUsable($result['question'], $answers) || $this->removed->contains((int) $category->external_id, $result['question'])) {
                    $skipped++;

                    continue;
                }

                Question::query()->updateOrCreate(
                    ['category_id' => $category->id, 'text_en' => $result['question']],
                    [
                        'text_el' => null,
                        'answers_en' => $answers,
                        'answers_el' => null,
                        'correct_index' => 0,
                        'difficulty' => self::DIFFICULTIES[$result['difficulty']] ?? null,
                    ],
                );
                $stored++;
            }

            return [$stored, $skipped];
        });
    }

    /**
     * The same rules the JSON importer applies: text that fits the column and four distinct answers.
     *
     * @param  list<string>  $answers
     */
    private function isUsable(string $question, array $answers): bool
    {
        if (trim($question) === '' || mb_strlen($question) > self::MAX_QUESTION_LENGTH) {
            return false;
        }

        $trimmed = array_map(trim(...), $answers);

        return count($trimmed) === Question::ANSWER_COUNT
            && ! in_array('', $trimmed, true)
            && count(array_unique(array_map(mb_strtolower(...), $trimmed))) === Question::ANSWER_COUNT;
    }

    /**
     * Run one request, pausing first so the run stays under OpenTDB's rate limit.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $request
     * @return TResult
     */
    private function paced(Closure $request): mixed
    {
        if ($this->requests > 0) {
            Sleep::for(self::SECONDS_BETWEEN_REQUESTS)->seconds();
        }

        $this->requests++;

        return $request();
    }
}
