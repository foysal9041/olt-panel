<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A to-do. Made for yourself it's a personal to-do; made for someone else
 * it's an assigned task, and the one who gave it follows its status and
 * progress. Its own timeline (work_updates) is the history, so ticking
 * to-dos doesn't fill the Activity Log.
 */
class Task extends Model
{
    /** status => [label, colour, icon] */
    public const STATUSES = [
        'todo' => ['To do', '#64748b', 'far fa-circle'],
        'in_progress' => ['In progress', '#d97706', 'fas fa-spinner'],
        'on_hold' => ['On hold', '#7c3aed', 'fas fa-pause-circle'],
        'done' => ['Done', '#16a34a', 'fas fa-check-circle'],
        'cancelled' => ['Cancelled', '#94a3b8', 'fas fa-ban'],
    ];

    public const OPEN = ['todo', 'in_progress', 'on_hold'];

    public const PRIORITIES = Ticket::PRIORITIES;

    protected $fillable = [
        'title', 'description', 'priority', 'status', 'progress', 'assigned_to', 'created_by', 'ticket_id', 'due_date', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function updates()
    {
        return $this->morphMany(WorkUpdate::class, 'subject')->latest('id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN);
    }

    /** Open first by due date (no date last), then the rest newest first. */
    public function scopeWorkOrder(Builder $q): Builder
    {
        return $q->orderByRaw("FIELD(status, 'in_progress', 'todo', 'on_hold', 'done', 'cancelled')")
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->latest('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function isPersonal(): bool
    {
        return $this->created_by === null || $this->created_by === $this->assigned_to;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date && $this->due_date->lt(today());
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? '#64748b';
    }

    public function statusIcon(): string
    {
        return self::STATUSES[$this->status][2] ?? 'far fa-circle';
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority][0] ?? $this->priority;
    }

    public function priorityColor(): string
    {
        return self::PRIORITIES[$this->priority][1] ?? '#64748b';
    }

    /** The one it's for, the one who gave it, and task-board users. */
    public function canBeSeenBy(User $user): bool
    {
        return $this->assigned_to === $user->id || $this->created_by === $user->id || $user->can('access-tasks-board');
    }
}
