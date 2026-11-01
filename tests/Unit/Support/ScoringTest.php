<?php

use App\Support\Scoring;

it('awards no points for a wrong answer regardless of speed', function () {
    expect(Scoring::points(false, 0, 20000))->toBe(0);
});

it('awards points that shrink with elapsed time', function (int $elapsedMs, int $expected) {
    expect(Scoring::points(true, $elapsedMs, 20000))->toBe($expected);
})->with([
    'instant answer' => [0, 1000],
    'quarter of the time' => [5000, 875],
    'half of the time' => [10000, 750],
    'at the buzzer' => [20000, 500],
    'after the buzzer' => [25000, 500],
]);

it('awards full points when the limit is zero', function () {
    expect(Scoring::points(true, 3000, 0))->toBe(1000);
});
