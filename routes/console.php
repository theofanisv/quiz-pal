<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('quiz:prune-rooms')->daily();
Schedule::command('quiz:sync-opentdb')->weekly()->withoutOverlapping()->runInBackground();
