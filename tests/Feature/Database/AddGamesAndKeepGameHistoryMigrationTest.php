<?php

use App\Enums\QuestionSource;
use App\Models\Answer;
use App\Models\Game;
use App\Models\Player;
use App\Models\Question;
use App\Models\Room;
use App\Models\Round;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('moves the rounds of every room that played into a game with the room settings', function () {
    $migrationsSinceGames = DB::table('migrations')->where('migration', '>=', '2026_11_02_195921')->count();
    Artisan::call('migrate:rollback', ['--step' => $migrationsSinceGames]);

    try {
        $finished = Room::factory()->finished()->create(['question_source' => QuestionSource::OpenTdb, 'total_rounds' => 2, 'seconds_per_question' => 15]);
        $playing = Room::factory()->playing()->create(['total_rounds' => 5, 'seconds_per_question' => 30]);
        $lobby = Room::factory()->create();
        $player = Player::factory()->for($finished)->create();
        $question = Question::factory()->create();
        $firstRoundId = DB::table('rounds')->insertGetId([
            'room_id' => $finished->id, 'round_number' => 1, 'question_id' => $question->id, 'answer_order' => '[0,1,2,3]',
            'started_at' => '2026-11-01 20:00:03.000', 'revealed_at' => '2026-11-01 20:00:10.000',
            'created_at' => '2026-11-01 20:00:00', 'updated_at' => '2026-11-01 20:00:10',
        ]);
        $secondRoundId = DB::table('rounds')->insertGetId([
            'room_id' => $finished->id, 'round_number' => 2, 'question_id' => $question->id, 'answer_order' => '[0,1,2,3]',
            'started_at' => '2026-11-01 20:00:20.000', 'revealed_at' => '2026-11-01 20:00:31.000',
            'created_at' => '2026-11-01 20:00:00', 'updated_at' => '2026-11-01 20:00:31',
        ]);
        $playingRoundId = DB::table('rounds')->insertGetId([
            'room_id' => $playing->id, 'round_number' => 1, 'question_id' => $question->id, 'answer_order' => '[0,1,2,3]',
            'started_at' => '2026-11-02 09:00:03.000', 'revealed_at' => null,
            'created_at' => '2026-11-02 09:00:00', 'updated_at' => '2026-11-02 09:00:00',
        ]);
        DB::table('answers')->insert([
            'round_id' => $firstRoundId, 'player_id' => $player->id, 'choice' => 1, 'is_correct' => true, 'points' => 900,
            'elapsed_ms' => 2000, 'answered_at' => '2026-11-01 20:00:05.000', 'created_at' => '2026-11-01 20:00:05', 'updated_at' => '2026-11-01 20:00:05',
        ]);

        Artisan::call('migrate');

        $finishedGame = Game::query()->where('room_id', $finished->id)->sole();
        $playingGame = Game::query()->where('room_id', $playing->id)->sole();
        expect(Game::query()->count())->toBe(2)
            ->and(Round::query()->orderBy('id')->pluck('game_id', 'id')->all())->toBe([
                $firstRoundId => $finishedGame->id,
                $secondRoundId => $finishedGame->id,
                $playingRoundId => $playingGame->id,
            ])
            ->and(Answer::query()->sole()->round_id)->toBe($firstRoundId)
            ->and($lobby->games()->exists())->toBeFalse();
        expect($finishedGame->only(['question_source', 'total_rounds', 'seconds_per_question']))
            ->toBe(['question_source' => QuestionSource::OpenTdb, 'total_rounds' => 2, 'seconds_per_question' => 15])
            ->and($finishedGame->created_at->toDateTimeString())->toBe('2026-11-01 20:00:00')
            ->and($finishedGame->finished_at->toDateTimeString())->toBe('2026-11-01 20:00:31');
        expect($playingGame->only(['total_rounds', 'seconds_per_question', 'finished_at']))
            ->toBe(['total_rounds' => 5, 'seconds_per_question' => 30, 'finished_at' => null])
            ->and($playingGame->created_at->toDateTimeString())->toBe('2026-11-02 09:00:00');
    } finally {
        Artisan::call('migrate');
    }
});

it('rolls back to the latest game of each room and drops the history the old tables cannot hold', function () {
    $room = Room::factory()->finished()->create();
    $earlier = Game::factory()->for($room)->finished()->create();
    $latest = Game::factory()->for($room)->finished()->create();
    $orphan = Game::factory()->finished()->create(['room_id' => null]);
    Round::factory()->for($earlier)->create();
    $kept = Round::factory()->for($latest)->create();
    $orphanRound = Round::factory()->for($orphan)->create();
    $player = Player::factory()->for($room)->create();
    Answer::factory()->for($kept)->for($player)->create();
    Answer::factory()->for($orphanRound)->create(['player_id' => null]);

    $migrationsSinceGames = DB::table('migrations')->where('migration', '>=', '2026_11_02_195921')->count();

    try {
        Artisan::call('migrate:rollback', ['--step' => $migrationsSinceGames]);

        expect(Schema::hasTable('games'))->toBeFalse()
            ->and(Schema::hasColumn('rounds', 'game_id'))->toBeFalse()
            ->and(DB::table('rounds')->get(['id', 'room_id'])->map(fn (object $round): array => (array) $round)->all())
            ->toBe([['id' => $kept->id, 'room_id' => $room->id]])
            ->and(DB::table('answers')->pluck('player_id')->all())->toBe([$player->id]);
    } finally {
        Artisan::call('migrate');
    }
});
