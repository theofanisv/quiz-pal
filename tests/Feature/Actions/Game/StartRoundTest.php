<?php

use App\Actions\Game\StartRound;
use App\Models\Question;
use App\Models\Room;
use App\Models\Round;

use function Pest\Laravel\freezeSecond;

it('counts the question as shown from the moment it appears', function () {
    freezeSecond();
    $room = Room::factory()->playing(0)->create();
    $question = Question::factory()->shown(2, now()->subDay())->create();
    Round::factory()->for($room)->for($question)->create(['round_number' => 1]);

    app(StartRound::class)->handle($room, 1);

    $question->refresh();
    expect($question->times_shown)->toBe(3)
        ->and($question->last_shown_at->equalTo(now()->addSeconds(3)))->toBeTrue();
});
