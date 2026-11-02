<?php

namespace App\Actions\Questions;

use App\Models\Answer;
use App\Models\Question;
use App\Models\Round;
use App\Support\Scoring;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * How much players seem to like each question, read from the game history instead of asking them. Every revealed
 * round of a question is one play, and four signals from those plays make up its score:
 *
 * - talk: how long the group stayed on the answer before the admin moved on, compared with the usual pause of
 *   that same game, so slow and fast admins weigh the same. A long pause means people stopped to talk about it.
 * - participation: the share of the players present who answered before the time ran out.
 * - challenge: from the points the answers earned (they count correctness and speed). Questions nobody gets and
 *   questions everyone gets instantly score low; questions that take some effort score high.
 * - wrong spread: how much the wrong answers cluster on one option, which makes a fun "gotcha".
 *
 * Each question gets few plays, so the score is pulled towards the average of all questions until it has more.
 *
 * @phpstan-type RoundAnswers array{answers: int, correct: int, points: int, wrong: array<int, int>}
 * @phpstan-type Measure array{plays: int, talk: ?float, participation: ?float, correct_rate: ?float, ease: ?float, wrong_spread: ?float}
 * @phpstan-type Likeness array{plays: int, talk: ?float, participation: ?float, correct_rate: ?float, ease: ?float, wrong_spread: ?float, score: float}
 */
class MeasureQuestionLikeness
{
    public const float TALK_WEIGHT = 0.40;

    public const float PARTICIPATION_WEIGHT = 0.25;

    public const float CHALLENGE_WEIGHT = 0.25;

    public const float WRONG_SPREAD_WEIGHT = 0.10;

    /**
     * A longer pause counts as this long: the admin probably stepped away.
     */
    public const int MAX_PAUSE_MILLISECONDS = 120_000;

    /**
     * Pauses a game needs before its usual pause means anything.
     */
    public const int MIN_PAUSES_PER_GAME = 3;

    /**
     * Answers a round needs before its pause can be a group talking.
     */
    public const int MIN_ANSWERS_TO_TALK = 2;

    /**
     * Wrong answers a question needs before their spread means anything.
     */
    public const int MIN_WRONG_ANSWERS = 2;

    /**
     * How many plays' worth of the average every score starts from.
     */
    public const int PRIOR_PLAYS = 3;

    /**
     * Every question that was played, most liked first, keyed by question id. `talk` is the question's pause as a
     * multiple of its games' usual pause; `participation`, `correct_rate`, `ease` and `wrong_spread` run from 0 to
     * 1 and `score` from 0 to 100. A signal without enough data is null.
     *
     * @return Collection<int, Likeness>
     */
    public function handle(): Collection
    {
        $rounds = Round::query()
            ->whereNotNull('started_at')
            ->with('game:id,total_rounds,finished_at')
            ->get(['id', 'game_id', 'round_number', 'question_id', 'started_at', 'revealed_at', 'players_present']);
        $answers = $this->answersPerRound();
        $talkRatios = $this->talkRatios($rounds, $answers);

        $measures = [];
        $raws = [];

        foreach ($rounds->filter(fn (Round $round): bool => $round->isRevealed())->groupBy('question_id') as $questionId => $plays) {
            $measures[(int) $questionId] = $measure = $this->measure($plays, $answers, $talkRatios);
            $raws[(int) $questionId] = $this->raw($measure);
        }

        $known = array_filter($raws, fn (?float $raw): bool => $raw !== null);
        $average = $known === [] ? 0.5 : array_sum($known) / count($known);

        $likeness = [];

        foreach ($measures as $questionId => $measure) {
            $likeness[$questionId] = [...$measure, 'score' => $this->score($measure['plays'], $raws[$questionId], $average)];
        }

        return collect($likeness)->sortByDesc('score');
    }

    /**
     * The answers of every round, keyed by round id, with the wrong ones counted per choice.
     *
     * @return array<int, RoundAnswers>
     */
    private function answersPerRound(): array
    {
        $rows = Answer::query()
            ->toBase()
            ->selectRaw('round_id, choice, is_correct, count(*) as picks, sum(points) as points')
            ->groupBy('round_id', 'choice', 'is_correct')
            ->get();

        $perRound = [];

        foreach ($rows as $row) {
            $roundId = (int) $row->round_id;
            $picks = (int) $row->picks;
            $perRound[$roundId] ??= ['answers' => 0, 'correct' => 0, 'points' => 0, 'wrong' => []];
            $perRound[$roundId]['answers'] += $picks;
            $perRound[$roundId]['points'] += (int) $row->points;

            if ((bool) $row->is_correct) {
                $perRound[$roundId]['correct'] += $picks;
            } else {
                $perRound[$roundId]['wrong'][(int) $row->choice] = $picks;
            }
        }

        return $perRound;
    }

    /**
     * Each round's pause as a multiple of the median pause of its game, keyed by round id. Only rounds that more
     * than one player answered count, and only in games with enough of them to have a usual pause.
     *
     * @param  Collection<int, Round>  $rounds
     * @param  array<int, RoundAnswers>  $answers
     * @return array<int, float>
     */
    private function talkRatios(Collection $rounds, array $answers): array
    {
        $ratios = [];

        foreach ($rounds->groupBy('game_id') as $gameRounds) {
            $byNumber = $gameRounds->keyBy('round_number');
            $pauses = [];

            foreach ($gameRounds as $round) {
                $pause = $this->pause($round, $byNumber);

                if ($pause !== null && ($answers[$round->id]['answers'] ?? 0) >= self::MIN_ANSWERS_TO_TALK) {
                    $pauses[$round->id] = $pause;
                }
            }

            if (count($pauses) < self::MIN_PAUSES_PER_GAME) {
                continue;
            }

            $median = (float) collect($pauses)->median();

            foreach ($pauses as $roundId => $pause) {
                $ratios[$roundId] = $pause / $median;
            }
        }

        return $ratios;
    }

    /**
     * Milliseconds the answer stayed on screen before the admin moved on, capped; null when nobody moved on (the
     * game was abandoned) or the record shows no pause (games recorded before the history kept every game).
     *
     * @param  Collection<int, Round>  $gameRounds  the started rounds of the game, keyed by round number
     */
    private function pause(Round $round, Collection $gameRounds): ?int
    {
        $advancedAt = $this->advancedAt($round, $gameRounds);

        if ($round->revealed_at === null || $advancedAt === null) {
            return null;
        }

        $pause = (int) $round->revealed_at->diffInMilliseconds($advancedAt, false);

        return $pause > 0 ? min($pause, self::MAX_PAUSE_MILLISECONDS) : null;
    }

    /**
     * When the admin moved on from the round: the next round is scheduled a few seconds ahead of its question,
     * and after the last round the game finishes.
     *
     * @param  Collection<int, Round>  $gameRounds  the started rounds of the game, keyed by round number
     */
    private function advancedAt(Round $round, Collection $gameRounds): ?CarbonInterface
    {
        $next = $gameRounds->get($round->round_number + 1);

        if ($next?->started_at !== null) {
            return $next->started_at->copy()->subSeconds(Round::START_DELAY_SECONDS);
        }

        return $round->round_number === $round->game->total_rounds ? $round->game->finished_at : null;
    }

    /**
     * The signals of one question over all its plays.
     *
     * @param  Collection<int, Round>  $plays
     * @param  array<int, RoundAnswers>  $answers
     * @param  array<int, float>  $talkRatios
     * @return Measure
     */
    private function measure(Collection $plays, array $answers, array $talkRatios): array
    {
        $logRatios = [];
        $present = 0;
        $answeredWhilePresent = 0;
        $answered = 0;
        $correct = 0;
        $points = 0;
        $wrong = [];

        foreach ($plays as $round) {
            $roundAnswers = $answers[$round->id] ?? ['answers' => 0, 'correct' => 0, 'points' => 0, 'wrong' => []];

            if (isset($talkRatios[$round->id])) {
                $logRatios[] = log($talkRatios[$round->id]);
            }

            if ($round->players_present !== null && $round->players_present > 0) {
                $present += $round->players_present;
                $answeredWhilePresent += min($roundAnswers['answers'], $round->players_present);
            }

            $answered += $roundAnswers['answers'];
            $correct += $roundAnswers['correct'];
            $points += $roundAnswers['points'];

            foreach ($roundAnswers['wrong'] as $choice => $picks) {
                $wrong[$choice] = ($wrong[$choice] ?? 0) + $picks;
            }
        }

        return [
            'plays' => $plays->count(),
            'talk' => $logRatios === [] ? null : exp(array_sum($logRatios) / count($logRatios)),
            'participation' => $present > 0 ? $answeredWhilePresent / (float) $present : null,
            'correct_rate' => $answered > 0 ? $correct / (float) $answered : null,
            'ease' => $answered > 0 ? $points / (float) ($answered * Scoring::MAX_POINTS) : null,
            'wrong_spread' => $this->wrongSpread($wrong),
        ];
    }

    /**
     * The weighted mean of the signals the question has data for, from 0 to 1; null when it has none.
     *
     * @param  Measure  $measure
     */
    private function raw(array $measure): ?float
    {
        $talk = $measure['talk'];
        $ease = $measure['ease'];

        return $this->weightedMean([
            [self::TALK_WEIGHT, $talk === null ? null : $this->clamp(0.5 + 0.5 * log($talk, 2))],
            [self::PARTICIPATION_WEIGHT, $measure['participation']],
            [self::CHALLENGE_WEIGHT, $ease === null ? null : 1 - abs(2 * $ease - 1)],
            [self::WRONG_SPREAD_WEIGHT, $measure['wrong_spread']],
        ]);
    }

    /**
     * 0 when the wrong answers spread evenly over the wrong options, 1 when they all picked the same one.
     *
     * @param  array<int, int>  $wrong  picks per wrong choice
     */
    private function wrongSpread(array $wrong): ?float
    {
        $wrongAnswers = array_sum($wrong);

        if ($wrong === [] || $wrongAnswers < self::MIN_WRONG_ANSWERS) {
            return null;
        }

        $evenShare = 1 / (Question::ANSWER_COUNT - 1);

        return (max($wrong) / $wrongAnswers - $evenShare) / (1 - $evenShare);
    }

    /**
     * The mean of the components that have a value, each by its weight; null when none has.
     *
     * @param  array<int, array{0: float, 1: float|null}>  $components  weight and value pairs
     */
    private function weightedMean(array $components): ?float
    {
        $known = array_filter($components, fn (array $component): bool => $component[1] !== null);

        if ($known === []) {
            return null;
        }

        $weights = array_sum(array_column($known, 0));

        return array_sum(array_map(fn (array $component): float => $component[0] * (float) $component[1], $known)) / $weights;
    }

    /**
     * The question's signals pulled towards the average of all questions, the more so the fewer plays it has.
     */
    private function score(int $plays, ?float $raw, float $average): float
    {
        $evidence = $raw === null ? 0 : $plays;

        return 100 * ($evidence * ($raw ?? 0.0) + self::PRIOR_PLAYS * $average) / ($evidence + self::PRIOR_PLAYS);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
