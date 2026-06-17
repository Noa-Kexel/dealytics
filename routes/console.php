<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Capture daily price snapshots for tracked games so the detail page can
// show a real price history over time.
Schedule::command('prices:snapshot')->dailyAt('06:00');
Schedule::command('alerts:check')->hourly();
