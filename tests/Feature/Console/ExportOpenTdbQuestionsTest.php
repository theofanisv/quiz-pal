<?php

use App\Models\Category;
use App\Models\Question;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\artisan;

it('exports the synced open trivia db questions and reports the counts', function () {
    $directory = sys_get_temp_dir().'/opentdb-export-'.uniqid();
    Category::factory()->openTdb(20)->has(Question::factory()->englishOnly()->count(2))->create(['name_en' => 'Mythology']);

    artisan('quiz:export-opentdb', ['path' => $directory])
        ->expectsOutputToContain('Exported 2 questions to 1 file(s) (0 translated, 0 to retranslate).')
        ->assertSuccessful();

    expect(File::exists("{$directory}/20-mythology.json"))->toBeTrue();
    File::deleteDirectory($directory);
});

it('fails when nothing has been synced yet', function () {
    artisan('quiz:export-opentdb', ['path' => sys_get_temp_dir().'/opentdb-export-'.uniqid()])
        ->expectsOutputToContain('quiz:sync-opentdb')
        ->assertFailed();
});

it('fails with the reason when an existing snapshot file is unreadable', function () {
    $directory = sys_get_temp_dir().'/opentdb-export-'.uniqid();
    File::ensureDirectoryExists($directory);
    file_put_contents("{$directory}/20-mythology.json", '{not json');
    Category::factory()->openTdb(20)->has(Question::factory()->englishOnly())->create(['name_en' => 'Mythology']);

    artisan('quiz:export-opentdb', ['path' => $directory])
        ->expectsOutputToContain('not a valid question file')
        ->assertFailed();
    File::deleteDirectory($directory);
});
