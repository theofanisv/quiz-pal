<?php

use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

it('rolls back the open trivia db migrations after a sync by discarding the mirrored questions', function () {
    Category::factory()->openTdb(13)->has(Question::factory()->englishOnly())->create();
    $builtIn = Category::factory()->has(Question::factory())->create();

    try {
        Artisan::call('migrate:rollback', ['--step' => 3]);

        expect(Schema::hasColumn('rooms', 'question_source'))->toBeFalse()
            ->and(Schema::hasColumn('categories', 'source'))->toBeFalse()
            ->and(Category::query()->pluck('id')->all())->toBe([$builtIn->id])
            ->and(Question::query()->whereNull('text_el')->count())->toBe(0)
            ->and(Question::query()->count())->toBe(1);
    } finally {
        Artisan::call('migrate');
    }
});
