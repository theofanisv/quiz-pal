<?php

namespace App\Models;

use Database\Factories\RoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['room_id', 'round_number', 'question_id', 'answer_order', 'started_at', 'ends_at', 'revealed_at'])]
class Round extends Model
{
    /** @use HasFactory<RoundFactory> */
    use HasFactory;

    /**
     * Seconds between scheduling a round and showing its question. The buffer lets every
     * device receive the broadcast first, so the question appears on all screens at the
     * same server instant and the speed bonus is fair.
     */
    public const int START_DELAY_SECONDS = 3;

    /**
     * Milliseconds after the reveal before the admin can move on, so every player gets a
     * moment to see the answers, even when the reveal broadcast reaches them late.
     */
    public const int ADVANCE_DELAY_MILLISECONDS = 2000;

    /**
     * Millisecond precision so that speed scoring is fair.
     */
    protected $dateFormat = self::DATE_FORMAT;

    public const string DATE_FORMAT = 'Y-m-d H:i:s.v';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'answer_order' => 'array',
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'revealed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * Whether the round has been scheduled: the question has been shown or is about to be.
     */
    public function isScheduled(): bool
    {
        return $this->started_at !== null;
    }

    /**
     * Whether the round is scheduled but its question is not on screen yet.
     */
    public function isPending(): bool
    {
        return $this->started_at !== null && now()->lessThan($this->started_at);
    }

    /**
     * Whether the question is on screen (the start moment has passed).
     */
    public function hasStarted(): bool
    {
        return $this->started_at !== null && now()->greaterThanOrEqualTo($this->started_at);
    }

    public function isRevealed(): bool
    {
        return $this->revealed_at !== null;
    }

    /**
     * Whether players can submit answers right now: shown, not yet revealed.
     */
    public function isOpen(): bool
    {
        return $this->hasStarted() && ! $this->isRevealed();
    }

    /**
     * Whole seconds until the question is shown (never negative).
     */
    public function secondsUntilStart(): int
    {
        if ($this->started_at === null) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInMilliseconds($this->started_at, false) / 1000));
    }

    /**
     * Milliseconds until the admin can move on from the reveal (never negative).
     */
    public function millisecondsUntilAdvance(): int
    {
        if ($this->revealed_at === null) {
            return 0;
        }

        $advanceAt = $this->revealed_at->copy()->addMilliseconds(self::ADVANCE_DELAY_MILLISECONDS);

        return max(0, (int) ceil(now()->diffInMilliseconds($advanceAt, false)));
    }

    /**
     * Whether every connected player of the room has answered this round.
     */
    public function everyoneAnswered(): bool
    {
        $connectedIds = $this->room->connectedPlayers()->pluck('id');

        if ($connectedIds->isEmpty()) {
            return false;
        }

        return $this->answers()->whereIn('player_id', $connectedIds)->count() >= $connectedIds->count();
    }

    public function hasExpired(): bool
    {
        return $this->ends_at !== null && now()->greaterThanOrEqualTo($this->ends_at);
    }

    /**
     * Whole seconds left before the round times out (never negative).
     */
    public function secondsLeft(): int
    {
        if ($this->ends_at === null) {
            return 0;
        }

        return max(0, (int) ceil(now()->diffInMilliseconds($this->ends_at, false) / 1000));
    }

    /**
     * The answers as every player sees them: shuffled once per round, each keyed by its original index.
     *
     * @return array<int, string>
     */
    public function displayedAnswers(): array
    {
        $answers = $this->question->answers;
        $displayed = [];

        foreach ($this->answer_order as $originalIndex) {
            $displayed[$originalIndex] = $answers[$originalIndex];
        }

        return $displayed;
    }
}
