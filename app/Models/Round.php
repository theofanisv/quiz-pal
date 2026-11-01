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

    public function hasStarted(): bool
    {
        return $this->started_at !== null;
    }

    public function isRevealed(): bool
    {
        return $this->revealed_at !== null;
    }

    /**
     * Whether players can still submit answers.
     */
    public function isOpen(): bool
    {
        return $this->hasStarted() && ! $this->isRevealed();
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
