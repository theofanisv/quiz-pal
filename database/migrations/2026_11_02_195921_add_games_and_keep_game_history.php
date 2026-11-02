<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every game becomes a row in `games`, and its rounds belong to the game instead of the room, so "Play again" and
 * the daily room prune no longer delete the history. A pruned room leaves its games behind with a null `room_id`,
 * and a deleted player leaves their answers behind with a null `player_id`.
 *
 * The backfill runs here rather than in a one-time operation because `game_id` becomes required and `room_id` goes
 * in this same migration (and the update script migrates before it runs operations). Rounds still in the database
 * all belong to one game per room: "Play again" deleted the earlier ones, and the settings only change in the lobby.
 *
 * SQLite rebuilds a table to change its keys, and dropping the old table cascades to the tables that reference it
 * (Laravel's "foreign_keys = off" has no effect inside the migration's transaction). So the keys of `answers` come
 * off before `rounds` is rebuilt and go back on afterwards, in both directions.
 *
 * Rolling back is lossy: it keeps only the latest game of each room, deletes the games of pruned rooms and the
 * answers of deleted players, and drops `games`. Restore the update snapshot instead when the history matters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question_source');
            $table->unsignedSmallInteger('total_rounds');
            $table->unsignedSmallInteger('seconds_per_question');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->unsignedBigInteger('game_id')->nullable()->after('id');
        });

        $this->moveRoundsIntoGames();

        $this->dropAnswerKeys();

        Schema::table('rounds', function (Blueprint $table) {
            // MySQL backs the room_id foreign key with the unique index, so the key has to go first.
            $table->dropForeign(['room_id']);
            $table->dropUnique(['room_id', 'round_number']);
            $table->dropColumn('room_id');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->unsignedBigInteger('game_id')->nullable(false)->change();
            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->unique(['game_id', 'round_number']);
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->unsignedBigInteger('player_id')->nullable()->change();
            $table->foreign('round_id')->references('id')->on('rounds')->cascadeOnDelete();
            $table->foreign('player_id')->references('id')->on('players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Plucked first: MySQL refuses a delete whose subquery reads the same table.
        $latestGameIds = DB::table('games')->whereNotNull('room_id')->groupBy('room_id')->selectRaw('max(id) as id')->pluck('id');

        DB::table('games')->whereNotIn('id', $latestGameIds)->delete();
        DB::table('answers')->whereNull('player_id')->delete();

        $this->dropAnswerKeys();

        Schema::table('rounds', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable()->after('id');
        });

        DB::table('rounds')->update([
            'room_id' => DB::table('games')->whereColumn('games.id', 'rounds.game_id')->select('room_id'),
        ]);

        Schema::table('rounds', function (Blueprint $table) {
            $table->dropForeign(['game_id']);
            $table->dropUnique(['game_id', 'round_number']);
            $table->dropColumn('game_id');
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable(false)->change();
            $table->foreign('room_id')->references('id')->on('rooms')->cascadeOnDelete();
            $table->unique(['room_id', 'round_number']);
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->unsignedBigInteger('player_id')->nullable(false)->change();
            $table->foreign('round_id')->references('id')->on('rounds')->cascadeOnDelete();
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
        });

        Schema::dropIfExists('games');
    }

    private function dropAnswerKeys(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropForeign(['round_id']);
            $table->dropForeign(['player_id']);
        });
    }

    /**
     * One game per room that has rounds, with the room's settings. It started when its rounds were created and,
     * when the room finished, ended at the last reveal.
     */
    private function moveRoundsIntoGames(): void
    {
        $rooms = DB::table('rooms')
            ->join('rounds', 'rounds.room_id', '=', 'rooms.id')
            ->groupBy('rooms.id', 'rooms.status', 'rooms.question_source', 'rooms.total_rounds', 'rooms.seconds_per_question')
            ->orderBy('rooms.id')
            ->get([
                'rooms.id', 'rooms.status', 'rooms.question_source', 'rooms.total_rounds', 'rooms.seconds_per_question',
                DB::raw('min(rounds.created_at) as started_at'),
                DB::raw('max(rounds.revealed_at) as last_revealed_at'),
            ]);

        foreach ($rooms as $room) {
            $finishedAt = $room->status === 'finished' ? $room->last_revealed_at : null;

            $gameId = DB::table('games')->insertGetId([
                'room_id' => $room->id,
                'question_source' => $room->question_source,
                'total_rounds' => $room->total_rounds,
                'seconds_per_question' => $room->seconds_per_question,
                'finished_at' => $finishedAt,
                'created_at' => $room->started_at,
                'updated_at' => $finishedAt ?? $room->started_at,
            ]);

            DB::table('rounds')->where('room_id', $room->id)->update(['game_id' => $gameId]);
        }
    }
};
