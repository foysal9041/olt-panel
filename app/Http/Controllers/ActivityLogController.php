<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Who did what, when: every sign-in, change and access update.
 *  - index(): everyone's activity (Settings → Activity Log);
 *  - mine():  the signed-in user's own activity (My Activity).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        return $this->render($request, null);
    }

    public function mine(Request $request)
    {
        return $this->render($request, $request->user());
    }

    private function render(Request $request, ?User $only)
    {
        $filters = [
            'user' => $only ? null : ($request->integer('user') ?: null),
            'action' => array_key_exists($request->query('action'), ActivityLog::ACTIONS) ? $request->query('action') : null,
            'subject' => $request->query('subject') ?: null,
            'from' => $this->date($request->query('from')),
            'to' => $this->date($request->query('to')),
            'q' => trim((string) $request->query('q', '')) ?: null,
        ];

        $base = ActivityLog::query()->when($only, fn ($q) => $q->where('user_id', $only->id));

        $logs = (clone $base)->with('user:id,name,username,role')
            ->when($filters['user'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['action'], fn ($q, $a) => $q->where('action', $a))
            ->when($filters['subject'], fn ($q, $s) => $q->where('subject_label', $s))
            ->when($filters['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($filters['q'], fn ($q, $term) => $q->where(fn ($w) => $w->where('description', 'like', "%{$term}%")->orWhere('ip_address', 'like', "%{$term}%")))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        $today = Carbon::today();
        $stats = [
            'today' => (clone $base)->whereDate('created_at', $today)->count(),
            'people' => (clone $base)->whereDate('created_at', $today)->distinct('user_id')->count('user_id'),
            'changes' => (clone $base)->whereDate('created_at', $today)->whereIn('action', ['created', 'updated', 'deleted', 'access'])->count(),
            'failed' => (clone $base)->where('action', 'login_failed')->where('created_at', '>=', now()->subDay())->count(),
        ];

        return view('activity.index', [
            'logs' => $logs,
            'filters' => $filters,
            'stats' => $stats,
            'mine' => (bool) $only,
            'users' => $only ? collect() : User::orderBy('name')->get(['id', 'name', 'username']),
            'subjects' => (clone $base)->whereNotNull('subject_label')->distinct()->orderBy('subject_label')->pluck('subject_label'),
        ]);
    }

    private function date($value): ?string
    {
        try {
            return $value ? Carbon::parse($value)->toDateString() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
