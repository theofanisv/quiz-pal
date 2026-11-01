<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('quiz:prune-rooms')->daily();
