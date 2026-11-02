<?php

namespace App\Models;

use App\Enums\PlayerColor;
use Database\Factories\PlayerFactory;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An anonymous participant of a room, authenticated through the "player" session guard.
 */
#[Fillable(['room_id', 'name', 'color', 'score', 'is_ready', 'is_connected', 'last_seen_at'])]
class Player extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    public const int MAX_NAME_LENGTH = 20;

    /**
     * Every player gets the first colour nobody in the room uses, unless one was
     * given explicitly. Done at insert time so batches of players (factories, tests)
     * see the rows inserted before them.
     */
    protected static function booted(): void
    {
        static::creating(function (Player $player): void {
            $player->color ??= PlayerColor::firstFree(
                static::query()->where('room_id', $player->room_id)->pluck('color'),
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'color' => PlayerColor::class,
            'score' => 'integer',
            'is_ready' => 'boolean',
            'is_connected' => 'boolean',
            'last_seen_at' => 'datetime',
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
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function correctAnswers(): HasMany
    {
        return $this->answers()->where('is_correct', true);
    }

    public function isAdminOf(Room $room): bool
    {
        return $room->isAdmin($this);
    }
}
