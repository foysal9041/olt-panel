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

Schedule::command('app:check-nttn-links')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('app:poll-switches')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Keep 90 days of switch port/status history.
Schedule::call(fn () => \App\Models\SwitchEvent::where('occurred_at', '<', now()->subDays(90))->delete())
    ->name('prune-switch-events')
    ->dailyAt('03:30');

// Rx/Tx history: delete in chunks so a large backlog doesn't lock the table.
Schedule::call(function () {
    $cutoff = now()->subDays(\App\Models\SwitchPortReading::RETENTION_DAYS);

    do {
        $deleted = \App\Models\SwitchPortReading::where('recorded_at', '<', $cutoff)->limit(5000)->delete();
    } while ($deleted > 0);
})
    ->name('prune-switch-port-readings')
    ->dailyAt('03:45');

// Employees past their last working day (termination letter): mark them
// inactive and block their panel login.
Schedule::call(function () {
    $left = \App\Models\Employee::where('status', true)->whereNotNull('left_on')->whereDate('left_on', '<', today())->get();
    foreach ($left as $employee) {
        $employee->update(['status' => false]);
        \App\Models\User::where('employee_id', $employee->id)->where('status', 1)->update(['status' => 0]);
        \App\Models\ActivityLog::record('updated', "{$employee->name} left on {$employee->left_on->format('d M Y')} — marked inactive, login blocked", $employee, null, null, false);
    }
})
    ->name('employees-left')
    ->dailyAt('00:20');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
