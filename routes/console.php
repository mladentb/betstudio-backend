<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Games sync runs every 5 minutes to keep data fresh
| Game status updates run every minute (live/finished)
|
*/

Schedule::command('games:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/sync.log'));

// Update game statuses every minute (scheduled -> live -> finished)
Schedule::command('games:update-statuses')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/game-statuses.log'));
