<?php

namespace App\Actions\Questions;

use App\Enums\QuestionSource;
use App\Models\Category;
use App\Models\Question;
use App\Support\RemovedOpenTdbQuestions;

/**
 * Delete the questions on the removed list (database/data/opentdb-removed.json) from a
 * database that imported them before they left the snapshot. Only rows of the matching
 * Open Trivia DB category go; their rounds, and the answers of those rounds, go with them
 * through the foreign keys. Running it again deletes nothing, so when the list grows a new
 * one-time operation can simply call it again.
 */
class DeleteRemovedOpenTdbQuestions
{
    public function __construct(private RemovedOpenTdbQuestions $removed) {}

    /**
     * @return int the number of deleted questions
     */
    public function handle(): int
    {
        $removed = $this->removed->all();

        if ($removed === []) {
            return 0;
        }

        $categoryIds = Category::query()
            ->forSource(QuestionSource::OpenTdb)
            ->whereIn('external_id', array_keys($removed))
            ->pluck('id', 'external_id');

        $deleted = 0;

        foreach ($categoryIds as $externalId => $categoryId) {
            $deleted += Question::query()
                ->where('category_id', $categoryId)
                ->whereIn('text_en', $removed[$externalId])
                ->delete();
        }

        return $deleted;
    }
}
