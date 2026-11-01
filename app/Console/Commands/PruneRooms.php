<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;

class PruneRooms extends Command
{
    protected $signature = 'quiz:prune-rooms {--hours=24 : Delete rooms that were last updated more than this many hours ago}';

    protected $description = 'Delete stale rooms together with their players, rounds and answers';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));

        $deleted = Room::query()
            ->where('updated_at', '<', now()->subHours($hours))
            ->delete();

        $this->components->info("Deleted {$deleted} room(s) idle for more than {$hours} hour(s).");

        return self::SUCCESS;
    }
}
