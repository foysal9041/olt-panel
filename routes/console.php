<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:check-olt-status')
    ->everyThirtySeconds()
    ->withoutOverlapping();

Schedule::command('app:probe-latency')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('app:prune-latency')
    ->dailyAt('03:15');

Schedule::command('app:poll-switches')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Keep 90 days of switch port/status history.
Schedule::call(fn () => \App\Models\SwitchEvent::where('occurred_at', '<', now()->subDays(90))->delete())
    ->name('prune-switch-events')
    ->dailyAt('03:30');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
