<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users with the "viewer" role can look at everything they're allowed to
 * see but change nothing: any write request, and any add/edit form page,
 * is refused. Buttons are hidden in the UI too (body.role-viewer), this is
 * the server-side guarantee.
 */
class ViewerReadOnly
{
    /** Writes a viewer may still make (their own session and password). */
    protected const ALLOWED_WRITES = [
        'logout',
        'profile.update',
        'password.update',
        'password.confirm',
        'tickets.progress',
        'tickets.task',
    ];

    /** Their own work: to-do list, tasks given to them, leave requests. */
    protected const ALLOWED_PREFIX = 'my.';

    /** Form pages a viewer may still open (their own profile). */
    protected const ALLOWED_FORMS = [
        'profile.edit',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isViewer()) {
            return $next($request);
        }

        $route = $request->route()?->getName() ?? '';

        $isWrite = ! $request->isMethodSafe();
        $isFormPage = (str_ends_with($route, '.create') || str_ends_with($route, '.edit'))
            && ! in_array($route, self::ALLOWED_FORMS, true);

        $own = str_starts_with($route, self::ALLOWED_PREFIX) || in_array($route, self::ALLOWED_WRITES, true);

        if (($isWrite && ! $own) || $isFormPage) {
            abort(403, 'This is a view-only account.');
        }

        return $next($request);
    }
}
