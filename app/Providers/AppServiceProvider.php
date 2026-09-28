<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

        Event::listen(function (Login $event) {
            ActivityLog::create([
                'user_id' => $event->user->id,
                'action' => 'login',
                'description' => "Logged in as {$event->user->name}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        Event::listen(function (Logout $event) {
            if (! $event->user) {
                return;
            }

            ActivityLog::create([
                'user_id' => $event->user->id,
                'action' => 'logout',
                'description' => "Logged out as {$event->user->name}",
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });

        // Admins always have full access, regardless of assigned module
        // permissions — every other ability check below is skipped for them.
        Gate::before(function (User $user) {
            return strtolower($user->role) === 'admin' ? true : null;
        });

        // One "access-{module}" ability per entry in config/modules.php,
        // e.g. access-olt, access-attendance — used by route middleware
        // (can:access-olt) and by the sidebar menu's GateFilter. Plus one
        // finer "access-{module}-{submodule}" ability per submodule, e.g.
        // access-attendance-leaves, so a user can be granted just a slice
        // of a module instead of all of it.
        foreach (config('modules', []) as $module => $definition) {
            Gate::define("access-{$module}", function (User $user) use ($module) {
                return $user->hasModuleAccess($module);
            });

            foreach (array_keys($definition['submodules'] ?? []) as $submodule) {
                Gate::define("access-{$module}-{$submodule}", function (User $user) use ($module, $submodule) {
                    return $user->hasModuleAccess($module, $submodule);
                });
            }
        }
    }
}
