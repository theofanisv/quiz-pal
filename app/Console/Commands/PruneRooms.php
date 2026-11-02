<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;

class PruneRooms extends Command
{
    protected $signature = 'quiz:prune-rooms {--days=7 : Delete rooms that were last updated more than this many days ago}';

    protected $description = 'Delete stale rooms together with their players; their games, rounds and answers stay as anonymous history';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = Room::query()
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();

        $this->components->info("Deleted {$deleted} room(s) idle for more than {$days} day(s).");

        return self::SUCCESS;
    }
}
