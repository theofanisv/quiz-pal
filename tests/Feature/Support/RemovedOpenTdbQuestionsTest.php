<?php

use App\Support\RemovedOpenTdbQuestions;

it('finds a listed question by its category and english text, ignoring case', function () {
    useRemovedOpenTdbQuestions([['category' => 9, 'text' => 'Is this wrong?']]);

    $removed = app(RemovedOpenTdbQuestions::class);

    expect($removed->contains(9, 'Is this wrong?'))->toBeTrue()
        ->and($removed->contains(9, 'IS THIS WRONG?'))->toBeTrue()
        ->and($removed->contains(10, 'Is this wrong?'))->toBeFalse()
        ->and($removed->contains(9, 'Is this right?'))->toBeFalse();
});

it('treats a missing list file as an empty list', function () {
    $removed = new RemovedOpenTdbQuestions(sys_get_temp_dir().'/opentdb-removed-missing-'.uniqid().'.json');

    expect($removed->all())->toBe([])
        ->and($removed->contains(9, 'Is this wrong?'))->toBeFalse();
});

it('rejects a list entry without a category id or a text', function (array $entry) {
    useRemovedOpenTdbQuestions([$entry]);

    app(RemovedOpenTdbQuestions::class)->all();
})->throws(RuntimeException::class, 'needs a positive integer "category" and a non-empty "text"')->with([
    'missing category' => [['text' => 'Is this wrong?']],
    'category given as text' => [['category' => '9', 'text' => 'Is this wrong?']],
    'blank text' => [['category' => 9, 'text' => ' ']],
]);

it('lists only questions that the bundled snapshot no longer contains', function () {
    $removed = app(RemovedOpenTdbQuestions::class)->all();
    $snapshot = collect(glob(database_path('data/opentdb/*.json')) ?: [])->mapWithKeys(function (string $path): array {
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return [$data['category']['external_id'] => array_map(fn (array $question): string => mb_strtolower($question['text']['en']), $data['questions'])];
    });

    $stillInSnapshot = collect($removed)->flatMap(
        fn (array $texts, int $externalId): array => array_values(array_intersect(array_map(mb_strtolower(...), $texts), $snapshot->get($externalId, []))),
    );

    expect($removed)->not->toBeEmpty()
        ->and(array_diff(array_keys($removed), $snapshot->keys()->all()))->toBe([])
        ->and($stillInSnapshot->all())->toBe([]);
});
