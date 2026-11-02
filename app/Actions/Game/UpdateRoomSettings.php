<?php

namespace App\Actions\Game;

use App\Actions\Game\Concerns\BroadcastsRoomUpdates;
use App\Enums\QuestionSource;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateRoomSettings
{
    use BroadcastsRoomUpdates;

    /**
     * The categories must belong to the chosen question source.
     *
     * @param  array{total_rounds?: mixed, seconds_per_question?: mixed, question_source?: mixed, categories?: mixed}  $input
     *
     * @throws GameException
     * @throws ValidationException
     */
    public function handle(Room $room, Player $actor, array $input): Room
    {
        if (! $room->isAdmin($actor)) {
            throw GameException::notAdmin();
        }

        if (! $room->isInLobby()) {
            throw GameException::roomNotInLobby();
        }

        $source = $input['question_source'] ?? null;

        $validated = Validator::make($input, [
            'total_rounds' => ['required', 'integer', 'between:'.Room::MIN_ROUNDS.','.Room::MAX_ROUNDS],
            'seconds_per_question' => ['required', 'integer', 'between:'.Room::MIN_SECONDS.','.Room::MAX_SECONDS],
            'question_source' => ['required', Rule::enum(QuestionSource::class)],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['integer', Rule::exists('categories', 'id')->where('source', is_string($source) ? $source : '')],
        ], attributes: [
            'total_rounds' => __('rounds'),
            'seconds_per_question' => __('seconds per question'),
            'question_source' => __('question source'),
            'categories' => __('categories'),
        ])->validate();

        $room->update([
            'total_rounds' => $validated['total_rounds'],
            'seconds_per_question' => $validated['seconds_per_question'],
            'question_source' => $validated['question_source'],
        ]);
        $room->categories()->sync($validated['categories']);

        $this->announce($room, 'settings-changed');

        return $room;
    }
}
