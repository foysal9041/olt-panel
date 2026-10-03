<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Zone;
use App\Support\DuplicateGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Support tickets: a customer's or the network's problem, from the call to
 * the fix. With the Tickets module you see, open and assign every ticket;
 * everyone else sees the tickets given to them (or opened by them) and
 * works on those.
 */
class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $view = $request->query('view', $user->can('access-tickets-manage') ? 'active' : 'mine');

        $base = Ticket::visibleTo($user);
        $query = (clone $base)->with(['customer', 'assignee', 'creator'])
            ->when($view === 'mine', fn ($q) => $q->where('assigned_to', $user->id)->active())
            ->when($view === 'active', fn ($q) => $q->active())
            ->when($view === 'overdue', fn ($q) => $q->overdue())
            ->when($view === 'unassigned', fn ($q) => $q->active()->whereNull('assigned_to'))
            ->when($view === 'done', fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->priority))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('assigned'), fn ($q) => $q->where('assigned_to', $request->assigned))
            ->when($request->filled('zone'), fn ($q) => $q->where('zone', $request->zone))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->q);
                $id = (int) preg_replace('/\D/', '', $term);
                $q->where(fn ($w) => $w->where('subject', 'like', "%{$term}%")->orWhere('contact_name', 'like', "%{$term}%")
                    ->orWhere('contact_phone', 'like', "%{$term}%")->orWhere('zone', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"))
                    ->when($id, fn ($x) => $x->orWhere('id', $id)));
            });

        $tickets = $query
            ->orderByRaw("FIELD(status, 'open', 'in_progress', 'on_hold', 'resolved', 'closed')")
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->orderBy('due_at')->latest('id')
            ->paginate(30)->withQueryString();

        $counts = [
            'mine' => (clone $base)->active()->where('assigned_to', $user->id)->count(),
            'active' => (clone $base)->active()->count(),
            'overdue' => (clone $base)->overdue()->count(),
            'unassigned' => (clone $base)->active()->whereNull('assigned_to')->count(),
            'done' => (clone $base)->whereIn('status', ['resolved', 'closed'])->count(),
            'resolved_today' => (clone $base)->whereDate('resolved_at', today())->count(),
        ];

        return view('tickets.index', [
            'tickets' => $tickets,
            'counts' => $counts,
            'view' => $view,
            'users' => $this->assignable(),
            'zones' => Zone::names(),
        ]);
    }

    public function create(Request $request)
    {
        return view('tickets.form', [
            'ticket' => new Ticket(['priority' => 'normal', 'category' => $request->query('category', 'no_internet'), 'source' => 'phone',
                'customer_id' => $request->integer('customer') ?: null]),
        ] + $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'open';
        $data['due_at'] = $data['due_at'] ?? now()->addHours(Ticket::PRIORITIES[$data['priority']][2]);

        $same = Ticket::where('subject', $data['subject'])->where('customer_id', $data['customer_id'] ?? null)->where('zone', $data['zone'] ?? null);
        if (DuplicateGuard::recent($same)) {
            return back()->withInput()->with('error', DuplicateGuard::message());
        }

        $ticket = DB::transaction(function () use ($data, $request) {
            $ticket = Ticket::create($data);
            $ticket->updates()->create(['user_id' => $request->user()->id, 'body' => null, 'changes' => ['opened' => true]
                + ($ticket->assigned_to ? ['assigned_to' => [null, $ticket->assignee->name]] : [])]);

            return $ticket;
        });

        return redirect()->route('tickets.show', $ticket)->with('success', "{$ticket->number()} opened" . ($ticket->assignee ? " and given to {$ticket->assignee->name}." : '.'));
    }

    public function show(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->canBeWorkedBy($request->user()), 403);
        $ticket->load(['customer', 'assignee', 'creator', 'updates.user', 'tasks.assignee']);

        return view('tickets.show', [
            'ticket' => $ticket,
            'users' => $this->assignable(),
            'history' => $ticket->customer_id
                ? Ticket::where('customer_id', $ticket->customer_id)->whereKeyNot($ticket->id)->latest('id')->limit(5)->get()
                : collect(),
        ]);
    }

    public function edit(Ticket $ticket)
    {
        return view('tickets.form', ['ticket' => $ticket] + $this->formData());
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $this->validated($request);
        $before = $ticket->only(['subject', 'priority', 'category', 'customer_id', 'zone', 'due_at']);
        $ticket->update($data);
        $changed = array_keys(array_diff_assoc(array_map('strval', $ticket->only(array_keys($before))), array_map('strval', $before)));
        if ($changed) {
            $ticket->updates()->create(['user_id' => $request->user()->id, 'changes' => ['edited' => $changed]]);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', "{$ticket->number()} saved.");
    }

    /**
     * Work on a ticket: comment, change status, hand it to someone. The
     * one it's given to may do this without the Tickets module.
     */
    public function progress(Request $request, Ticket $ticket)
    {
        $user = $request->user();
        abort_unless($ticket->canBeWorkedBy($user), 403);
        $manage = $user->can('access-tickets-manage');

        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Ticket::STATUSES))],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('status', 1)],
            'body' => 'nullable|string|max:5000',
        ]);

        $changes = [];
        if (filled($data['status'] ?? null) && $data['status'] !== $ticket->status) {
            if ($data['status'] === 'closed' && ! $manage) {
                return back()->with('error', 'Mark it Resolved — closing is done by the one who manages tickets.');
            }
            $changes['status'] = [$ticket->status, $data['status']];
            $ticket->status = $data['status'];
            $ticket->resolved_at = in_array($data['status'], ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null;
            $ticket->closed_at = $data['status'] === 'closed' ? now() : null;
        }
        if ($manage && $request->has('assigned_to') && (int) ($data['assigned_to'] ?? 0) !== (int) $ticket->assigned_to) {
            $to = User::find($data['assigned_to'] ?? null);
            $changes['assigned_to'] = [$ticket->assignee?->name, $to?->name];
            $ticket->assigned_to = $to?->id;
        }
        $body = trim((string) ($data['body'] ?? ''));

        if (! $changes && $body === '') {
            return back()->with('error', 'Nothing to save — write a note or change the status.');
        }

        DB::transaction(function () use ($ticket, $changes, $body, $user) {
            $ticket->save();
            $ticket->updates()->create(['user_id' => $user->id, 'body' => $body !== '' ? $body : null, 'changes' => $changes ?: null]);
        });

        return back()->with('success', isset($changes['status']) ? "{$ticket->number()} is now {$ticket->statusLabel()}." : 'Update added.');
    }

    public function destroy(Request $request, Ticket $ticket)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $number = $ticket->number();
        DB::transaction(function () use ($ticket) {
            $ticket->updates()->delete();
            $ticket->delete();
        });

        return redirect()->route('tickets.index')->with('success', "{$number} removed.");
    }

    /** Make a task out of a ticket, for someone to do. */
    public function makeTask(Request $request, Ticket $ticket)
    {
        abort_unless($ticket->canBeWorkedBy($request->user()), 403);
        $data = $request->validate([
            'assigned_to' => ['required', Rule::exists('users', 'id')->where('status', 1)],
            'title' => 'required|string|max:255',
            'due_date' => 'nullable|date',
        ]);
        if ((int) $data['assigned_to'] !== $request->user()->id && ! $request->user()->can('access-tasks-assign')) {
            abort(403, 'You can only make tasks for yourself.');
        }

        $task = Task::create($data + ['ticket_id' => $ticket->id, 'created_by' => $request->user()->id, 'priority' => $ticket->priority]);
        $ticket->updates()->create(['user_id' => $request->user()->id, 'changes' => ['task' => [$task->title, $task->assignee->name]]]);

        return back()->with('success', "Task given to {$task->assignee->name}.");
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'source' => ['nullable', Rule::in(array_keys(Ticket::SOURCES))],
            'customer_id' => 'nullable|exists:customers,id',
            'zone' => 'nullable|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:30',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('status', 1)],
            'due_at' => 'nullable|date',
        ]);

        // A MAC client is a zone: fill the zone from it.
        if (! filled($data['zone'] ?? null) && filled($data['customer_id'] ?? null)) {
            $data['zone'] = Customer::find($data['customer_id'])?->zone;
        }

        return $data;
    }

    protected function formData(): array
    {
        return [
            'customers' => Customer::orderBy('customer_type')->orderBy('name')->get(['id', 'name', 'customer_type', 'zone', 'phone', 'contact_person']),
            'users' => $this->assignable(),
            'zones' => Zone::names(),
        ];
    }

    protected function assignable()
    {
        return User::where('status', 1)->orderBy('name')->get(['id', 'name', 'role']);
    }
}
