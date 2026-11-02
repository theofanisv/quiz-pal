<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('quiz:prune-rooms')->daily();

Schedule::command('backup:run')->environments('production')->dailyAt('02:00');
Schedule::command('backup:clean')->environments('production')->dailyAt('03:00');
Schedule::command('backup:monitor')->environments('production')->dailyAt('04:00');
