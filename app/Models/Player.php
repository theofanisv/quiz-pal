<?php

namespace App\Models;

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
#[Fillable(['room_id', 'name', 'score', 'is_ready', 'is_connected', 'last_seen_at'])]
class Player extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    /** @use HasFactory<PlayerFactory> */
    use HasFactory;

    public const int MAX_NAME_LENGTH = 20;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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

    public function isAdminOf(Room $room): bool
    {
        return $room->isAdmin($this);
    }
}
