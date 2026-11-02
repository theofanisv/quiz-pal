<?php

namespace App\Actions\Game;

use App\Actions\Game\Concerns\BroadcastsRoomUpdates;
use App\Enums\RoomStatus;
use App\Exceptions\GameException;
use App\Models\Player;
use App\Models\Question;
use App\Models\Room;
use App\Support\LiveUpdates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StartGame
{
    use BroadcastsRoomUpdates;

    public function __construct(
        private StartRound $startRound,
        private LiveUpdates $liveUpdates,
    ) {}

    /**
     * Pick the questions, create every round up front and open the first one.
     *
     * @throws GameException
     */
    public function handle(Room $room, Player $actor): Room
    {
        if (! $room->isAdmin($actor)) {
            throw GameException::notAdmin();
        }

        if (! $room->isInLobby()) {
            throw GameException::roomNotInLobby();
        }

        if (! $this->liveUpdates->isAvailable()) {
            throw GameException::liveUpdatesUnavailable();
        }

        if (! $room->everyoneReady()) {
            throw GameException::notEveryoneReady();
        }

        $questionIds = $this->pickQuestionIds($room);

        DB::transaction(function () use ($room, $questionIds): void {
            $room->players()->update(['score' => 0]);

            foreach ($questionIds as $index => $questionId) {
                $answerOrder = range(0, Question::ANSWER_COUNT - 1);
                shuffle($answerOrder);

                $room->rounds()->create([
                    'round_number' => $index + 1,
                    'question_id' => $questionId,
                    'answer_order' => $answerOrder,
                ]);
            }

            $room->update(['status' => RoomStatus::Playing]);

            $this->startRound->handle($room, 1);
        });

        $this->announce($room, 'started');

        return $room;
    }

    /**
     * Spread the questions evenly over the selected categories, taking the questions of each category that
     * were never shown or shown longest ago first, then shuffle them. When the rounds do not divide evenly, random categories get the
     * extra questions. Questions a player reported are left out until someone reviews them.
     *
     * @return array<int, int>
     *
     * @throws GameException
     */
    private function pickQuestionIds(Room $room): array
    {
        $categoryIds = $room->categories()->pluck('categories.id');

        /** @var Collection<int, Collection<int, int>> $idsPerCategory */
        $idsPerCategory = Question::query()
            ->whereIn('category_id', $categoryIds)
            ->inRotation()
            ->leastRecentlyShownFirst()
            ->get(['id', 'category_id'])
            ->groupBy('category_id')
            ->map(fn (Collection $questions): Collection => $questions->pluck('id'))
            ->shuffle();

        $available = $idsPerCategory->sum(fn (Collection $ids): int => $ids->count());

        if ($available < $room->total_rounds) {
            throw GameException::notEnoughQuestions($available);
        }

        $selected = [];
        $queues = $idsPerCategory->map(fn (Collection $ids): array => $ids->all())->all();

        while (count($selected) < $room->total_rounds) {
            foreach ($queues as $categoryIndex => $ids) {
                if ($ids === []) {
                    continue;
                }

                $selected[] = array_shift($queues[$categoryIndex]);

                if (count($selected) === $room->total_rounds) {
                    break;
                }
            }
        }

        shuffle($selected);

        return $selected;
    }
}
