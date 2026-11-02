<?php

use App\Actions\Game\RevealRound;
use App\Models\Answer;
use App\Models\Player;
use App\Models\Room;
use App\Models\Round;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

it('adds each answer\'s points to its player only once', function () {
    $room = Room::factory()->playing()->withAdmin()->create();
    $round = Round::factory()->forRoom($room)->started()->create();
    $player = Player::factory()->for($room)->create(['score' => 100]);
    Answer::factory()->for($round)->for($player)->create(['is_correct' => true, 'points' => 750]);
    Answer::factory()->for($round)->for($room->admin)->create(['is_correct' => false, 'points' => 0]);

    $first = app(RevealRound::class)->handle($round);
    $second = app(RevealRound::class)->handle($round->fresh());

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($round->fresh()->isRevealed())->toBeTrue()
        ->and($player->fresh()->score)->toBe(850)
        ->and($room->admin->fresh()->score)->toBe(0);
});

it('adds the points even when another answer is saved during the reveal', function () {
    // Production runs SQLite in WAL mode, where a second connection can commit while the reveal is in progress.
    $path = tempnam(sys_get_temp_dir(), 'quizpal-');
    foreach (['game', 'other-request'] as $connection) {
        config()->set("database.connections.{$connection}", [
            ...config('database.connections.sqlite'),
            'database' => $path,
            'journal_mode' => 'wal',
            'busy_timeout' => 5000,
        ]);
    }
    Artisan::call('migrate', ['--database' => 'game']);
    DB::setDefaultConnection('game');
    $room = Room::factory()->playing()->withAdmin()->create();
    $round = Round::factory()->forRoom($room)->started()->create();
    $player = Player::factory()->for($room)->create(['score' => 100]);
    Answer::factory()->for($round)->for($player)->create(['is_correct' => true, 'points' => 750]);
    $saved = false;
    DB::listen(function (QueryExecuted $query) use ($round, $room, &$saved): void {
        if ($saved || $query->connectionName !== 'game' || ! str_contains($query->sql, 'from "answers"')) {
            return;
        }

        $saved = true;
        DB::connection('other-request')->table('answers')->insert([
            'round_id' => $round->id,
            'player_id' => $room->admin->id,
            'choice' => 0,
            'is_correct' => false,
            'points' => 0,
            'answered_at' => '2026-11-01 21:50:07.000',
            'created_at' => '2026-11-01 21:50:07',
            'updated_at' => '2026-11-01 21:50:07',
        ]);
    });

    try {
        $revealed = app(RevealRound::class)->handle($round);

        expect($revealed)->toBeTrue()
            ->and($player->fresh()->score)->toBe(850);
    } finally {
        DB::setDefaultConnection('sqlite');
        DB::disconnect('game');
        DB::disconnect('other-request');
        File::delete([$path, "{$path}-wal", "{$path}-shm"]);
    }
});
