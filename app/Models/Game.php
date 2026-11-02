<?php

namespace App\Models;

use App\Enums\QuestionSource;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One game played in a room, kept as history after "Play again" and after the room is pruned
 * (then its room is null). It holds the settings the game was played with; it started when it was
 * created.
 */
#[Fillable(['room_id', 'question_source', 'total_rounds', 'seconds_per_question', 'finished_at'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_source' => QuestionSource::class,
            'total_rounds' => 'integer',
            'seconds_per_question' => 'integer',
            'finished_at' => 'datetime',
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
     * @return HasMany<Round, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(Round::class)->orderBy('round_number');
    }

    /**
     * How many questions each player answered correctly in this game, keyed by player id.
     * Players who got none are missing.
     *
     * @return array<int, int>
     */
    public function correctAnswerCounts(): array
    {
        return Answer::query()
            ->whereRelation('round', 'game_id', $this->id)
            ->where('is_correct', true)
            ->whereNotNull('player_id')
            ->groupBy('player_id')
            ->selectRaw('player_id, count(*) as correct_answers')
            ->pluck('correct_answers', 'player_id')
            ->mapWithKeys(fn (mixed $count, int|string $playerId): array => [(int) $playerId => (int) $count])
            ->all();
    }
}
