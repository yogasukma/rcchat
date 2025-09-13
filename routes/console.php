<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the token pruning command to run hourly
Schedule::command('tokens:prune')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
