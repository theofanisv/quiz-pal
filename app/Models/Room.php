<?php

namespace App\Models;

use App\Enums\QuestionSource;
use App\Enums\RoomStatus;
use App\Support\RoomCode;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'admin_player_id', 'status', 'total_rounds', 'seconds_per_question', 'current_round', 'question_source'])]
#[RouteKey('code')]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    public const int MIN_ROUNDS = 1;

    public const int MAX_ROUNDS = 30;

    public const int MIN_SECONDS = 5;

    public const int MAX_SECONDS = 60;

    public const int MAX_PLAYERS = 20;

    /**
     * Accept room codes typed in lowercase in the URL.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, is_string($value) ? RoomCode::normalize($value) : $value, $field);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'question_source' => QuestionSource::class,
            'total_rounds' => 'integer',
            'seconds_per_question' => 'integer',
            'current_round' => 'integer',
        ];
    }

    /**
     * @return HasMany<Player, $this>
     */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class)->orderBy('id');
    }

    /**
     * @return HasMany<Player, $this>
     */
    public function connectedPlayers(): HasMany
    {
        return $this->players()->where('is_connected', true);
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'admin_player_id');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * Every game played in the room, kept after "Play again".
     *
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * The game being played, or the one that just finished; back in the lobby it is the previous game.
     *
     * @return HasOne<Game, $this>
     */
    public function currentGame(): HasOne
    {
        return $this->games()->one()->latestOfMany();
    }

    public function currentRound(): ?Round
    {
        if ($this->current_round === 0) {
            return null;
        }

        return $this->currentGame?->rounds()->where('round_number', $this->current_round)->with('question')->first();
    }

    public function isAdmin(Player $player): bool
    {
        return $this->admin_player_id !== null && $this->admin_player_id === $player->id;
    }

    public function isInLobby(): bool
    {
        return $this->status === RoomStatus::Lobby;
    }

    public function isPlaying(): bool
    {
        return $this->status === RoomStatus::Playing;
    }

    public function isFinished(): bool
    {
        return $this->status === RoomStatus::Finished;
    }

    public function isLastRound(): bool
    {
        return $this->current_round >= $this->total_rounds;
    }

    /**
     * Whether every connected player has pressed ready.
     */
    public function everyoneReady(): bool
    {
        $connected = $this->connectedPlayers()->get();

        return $connected->isNotEmpty() && $connected->every(fn (Player $player): bool => $player->is_ready);
    }
}
