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


Schedule::command('app:check-nttn-links')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('app:poll-switches')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Old history — graphs, latency checks, optical readings, port events — past
// what Settings → Data Retention keeps (skipped while auto delete is off).
Schedule::command('app:prune-data')
    ->dailyAt('03:30')
    ->withoutOverlapping();

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
