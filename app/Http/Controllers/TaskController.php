<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Support\DuplicateGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * My Work: everyone's own to-do list, the tasks given to them and the
 * tasks they gave others (with where each one is now). Giving tasks to
 * others needs "Assign tasks"; seeing everyone's needs "Team board".
 */
class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab', 'mine');
        if ($tab === 'board' && ! $user->can('access-tasks-board')) {
            $tab = 'mine';
        }
        $showDone = $request->boolean('done');

        $query = Task::with(['assignee', 'creator', 'ticket'])->withCount('updates')
            ->when($tab === 'mine', fn ($q) => $q->where('assigned_to', $user->id))
            ->when($tab === 'given', fn ($q) => $q->where('created_by', $user->id)->where('assigned_to', '!=', $user->id))
            ->when($tab === 'board' && $request->filled('person'), fn ($q) => $q->where('assigned_to', $request->person))
            ->when(! $showDone, fn ($q) => $q->where(fn ($w) => $w->open()->orWhere('completed_at', '>=', now()->subDays(2))))
            ->workOrder();

        $tasks = $query->limit(300)->get();

        $mine = Task::where('assigned_to', $user->id);
        $given = Task::where('created_by', $user->id)->where('assigned_to', '!=', $user->id);
        $counts = [
            'mine' => (clone $mine)->open()->count(),
            'overdue' => (clone $mine)->open()->whereDate('due_date', '<', today())->count(),
            'today' => (clone $mine)->open()->whereDate('due_date', today())->count(),
            'done_week' => (clone $mine)->where('status', 'done')->where('completed_at', '>=', now()->startOfWeek())->count(),
            'given' => (clone $given)->open()->count(),
            'board' => $user->can('access-tasks-board') ? Task::open()->count() : 0,
        ];

        // The team board: open tasks per person.
        $people = $tab === 'board'
            ? User::where('status', 1)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['user' => $u, 'open' => Task::where('assigned_to', $u->id)->open()->count(),
                    'overdue' => Task::where('assigned_to', $u->id)->open()->whereDate('due_date', '<', today())->count()])
            : collect();

        return view('tasks.index', [
            'tasks' => $tasks,
            'tab' => $tab,
            'counts' => $counts,
            'people' => $people,
            'showDone' => $showDone,
            'users' => $this->assignable($user),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'priority' => ['nullable', Rule::in(array_keys(Task::PRIORITIES))],
            'due_date' => 'nullable|date',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('status', 1)],
        ]);
        $data['assigned_to'] = (int) ($data['assigned_to'] ?? $user->id);
        if ($data['assigned_to'] !== $user->id && ! $user->can('access-tasks-assign')) {
            abort(403, 'You can add to-dos for yourself; giving tasks to others needs "Assign tasks".');
        }
        $data['priority'] = $data['priority'] ?? 'normal';
        $data['created_by'] = $user->id;

        $same = Task::where('title', $data['title'])->where('assigned_to', $data['assigned_to'])->where('created_by', $user->id);
        if (DuplicateGuard::recent($same, 30)) {
            return back()->with('error', DuplicateGuard::message());
        }

        $task = Task::create($data);

        return back()->with('success', $task->isPersonal() ? 'Added to your to-do list.' : "Task given to {$task->assignee->name}.");
    }

    public function show(Request $request, Task $task)
    {
        abort_unless($task->canBeSeenBy($request->user()), 403);
        $task->load(['assignee', 'creator', 'ticket', 'updates.user']);

        return view('tasks.show', ['task' => $task, 'users' => $this->assignable($request->user())]);
    }

    /** Status, progress and a note — by the one doing it (or the one who gave it). */
    public function progress(Request $request, Task $task)
    {
        $user = $request->user();
        abort_unless($task->assigned_to === $user->id || $task->created_by === $user->id || $user->isAdmin(), 403);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Task::STATUSES))],
            'progress' => 'nullable|integer|min:0|max:100',
            'body' => 'nullable|string|max:5000',
        ]);

        $changes = $this->applyStatus($task, $data['status'] ?? null, $data['progress'] ?? null);
        $body = trim((string) ($data['body'] ?? ''));
        if (! $changes && $body === '') {
            return back()->with('error', 'Nothing to save — write a note or change the status.');
        }

        DB::transaction(function () use ($task, $changes, $body, $user) {
            $task->save();
            $task->updates()->create(['user_id' => $user->id, 'body' => $body !== '' ? $body : null, 'changes' => $changes ?: null]);
        });

        return back()->with('success', isset($changes['status']) ? "“{$task->title}” is now {$task->statusLabel()}." : 'Update saved.');
    }

    /** The tick box on the list: done / not done. */
    public function toggle(Request $request, Task $task)
    {
        $user = $request->user();
        abort_unless($task->assigned_to === $user->id || $task->created_by === $user->id, 403);

        $changes = $this->applyStatus($task, $task->status === 'done' ? 'todo' : 'done', null);
        DB::transaction(function () use ($task, $changes, $user) {
            $task->save();
            if (! $task->isPersonal()) {
                $task->updates()->create(['user_id' => $user->id, 'changes' => $changes]);
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['status' => $task->status, 'label' => $task->statusLabel()]);
        }

        return back();
    }

    public function update(Request $request, Task $task)
    {
        $user = $request->user();
        abort_unless($task->created_by === $user->id || $user->isAdmin(), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'priority' => ['required', Rule::in(array_keys(Task::PRIORITIES))],
            'due_date' => 'nullable|date',
            'assigned_to' => ['required', Rule::exists('users', 'id')->where('status', 1)],
        ]);
        if ((int) $data['assigned_to'] !== $user->id && (int) $data['assigned_to'] !== $task->assigned_to && ! $user->can('access-tasks-assign')) {
            abort(403);
        }

        $changes = [];
        if ((int) $data['assigned_to'] !== $task->assigned_to) {
            $changes['assigned_to'] = [$task->assignee?->name, User::find($data['assigned_to'])?->name];
        }
        foreach (['title', 'due_date', 'priority'] as $f) {
            $old = $f === 'due_date' ? $task->due_date?->toDateString() : $task->$f;
            if ((string) $old !== (string) ($data[$f] ?? '')) {
                $changes[$f] = [$old, $data[$f] ?? null];
            }
        }

        DB::transaction(function () use ($task, $data, $changes, $user) {
            $task->update($data);
            if ($changes && ! $task->isPersonal()) {
                $task->updates()->create(['user_id' => $user->id, 'changes' => $changes]);
            }
        });

        return back()->with('success', 'Task saved.');
    }

    public function destroy(Request $request, Task $task)
    {
        $user = $request->user();
        abort_unless($task->created_by === $user->id || $user->isAdmin() || ($task->isPersonal() && $task->assigned_to === $user->id), 403);

        DB::transaction(function () use ($task) {
            $task->updates()->delete();
            $task->delete();
        });

        // From the task's own page go back to the list (the page is gone).
        return $request->input('back') === 'list'
            ? redirect()->route('my.tasks.index')->with('success', 'Task removed.')
            : back()->with('success', 'Task removed.');
    }

    /** Set status and progress together; returns what changed. */
    protected function applyStatus(Task $task, ?string $status, ?int $progress): array
    {
        $changes = [];
        if ($status && $status !== $task->status) {
            $changes['status'] = [$task->status, $status];
            $task->status = $status;
            $task->completed_at = $status === 'done' ? now() : null;
            if ($status === 'done') {
                $progress = 100;
            } elseif ($status === 'todo' && $progress === null) {
                $progress = 0;
            }
        }
        if ($progress !== null && $progress !== $task->progress) {
            $changes['progress'] = [$task->progress, $progress];
            $task->progress = $progress;
        }

        return $changes;
    }

    /** Who the user may give tasks to: anyone active with "Assign tasks", else only themselves. */
    protected function assignable(User $user)
    {
        return $user->can('access-tasks-assign')
            ? User::where('status', 1)->orderBy('name')->get(['id', 'name', 'role'])
            : collect([$user]);
    }
}
