<?php

namespace App\Support;

use JsonException;
use RuntimeException;

/**
 * The Open Trivia DB questions taken out of the snapshot because their source is wrong,
 * out of date or has more than one correct answer, listed in database/data/opentdb-removed.json
 * by OpenTDB category id and English text.
 *
 * `quiz:sync-opentdb` and `quiz:export-opentdb` skip them, so a later refresh of the snapshot
 * does not bring them back, and `DeleteRemovedOpenTdbQuestions` deletes them from databases
 * that imported them before they were removed. A missing file is an empty list.
 */
class RemovedOpenTdbQuestions
{
    /** @var array<int, list<string>>|null */
    private ?array $texts = null;

    public function __construct(private ?string $path = null) {}

    public function path(): string
    {
        return $this->path ?? database_path('data/opentdb-removed.json');
    }

    /**
     * The removed English texts, keyed by OpenTDB category id.
     *
     * @return array<int, list<string>>
     *
     * @throws RuntimeException when the file is not a valid list
     */
    public function all(): array
    {
        return $this->texts ??= $this->load();
    }

    /**
     * Whether the question is on the list; the English text is compared without regard to case.
     */
    public function contains(int $externalId, string $text): bool
    {
        $needle = mb_strtolower($text);

        foreach ($this->all()[$externalId] ?? [] as $removed) {
            if (mb_strtolower($removed) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, list<string>>
     */
    private function load(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("{$path} is not valid JSON: {$exception->getMessage()}", previous: $exception);
        }

        if (! is_array($data) || ! is_array($data['questions'] ?? null)) {
            throw new RuntimeException("{$path} needs a \"questions\" list.");
        }

        $texts = [];

        foreach ($data['questions'] as $index => $entry) {
            $category = is_array($entry) ? ($entry['category'] ?? null) : null;
            $text = is_array($entry) ? ($entry['text'] ?? null) : null;

            if (! is_int($category) || $category < 1 || ! is_string($text) || trim($text) === '') {
                throw new RuntimeException("{$path}: entry {$index} needs a positive integer \"category\" and a non-empty \"text\".");
            }

            $texts[$category][] = $text;
        }

        return $texts;
    }
}
