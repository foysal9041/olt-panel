<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use LogsActivity;

    public const CATEGORIES = [
        'no_internet' => ['No internet / line down', 'fas fa-unlink'],
        'slow' => ['Slow speed', 'fas fa-tachometer-alt'],
        'fiber_cut' => ['Fiber cut', 'fas fa-cut'],
        'device' => ['ONU / router problem', 'fas fa-hdd'],
        'link_down' => ['Link down (NTTN / upstream)', 'fas fa-project-diagram'],
        'new_connection' => ['New connection', 'fas fa-plug'],
        'shifting' => ['Shifting', 'fas fa-truck'],
        'billing' => ['Billing', 'fas fa-file-invoice'],
        'other' => ['Other', 'fas fa-question-circle'],
    ];

    /** priority => [label, colour, hours to fix] */
    public const PRIORITIES = [
        'urgent' => ['Urgent', '#dc2626', 4],
        'high' => ['High', '#ea580c', 8],
        'normal' => ['Normal', '#0284c7', 24],
        'low' => ['Low', '#64748b', 72],
    ];

    /** status => [label, colour, icon] */
    public const STATUSES = [
        'open' => ['New', '#dc2626', 'fas fa-folder-open'],
        'in_progress' => ['In progress', '#d97706', 'fas fa-spinner'],
        'on_hold' => ['On hold', '#7c3aed', 'fas fa-pause-circle'],
        'resolved' => ['Resolved', '#16a34a', 'fas fa-check-circle'],
        'closed' => ['Closed', '#64748b', 'fas fa-lock'],
    ];

    public const ACTIVE = ['open', 'in_progress', 'on_hold'];

    public const SOURCES = [
        'phone' => 'Phone call',
        'whatsapp' => 'WhatsApp',
        'walk_in' => 'Walk-in',
        'noc' => 'NOC monitoring',
        'email' => 'Email',
        'other' => 'Other',
    ];

    protected $fillable = [
        'subject', 'description', 'category', 'priority', 'status', 'source', 'customer_id', 'zone',
        'contact_name', 'contact_phone', 'assigned_to', 'created_by', 'due_at', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updates()
    {
        return $this->morphMany(WorkUpdate::class, 'subject')->latest('id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', self::ACTIVE);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->active()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    /** Tickets this user may open: all with the module, else theirs. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->can('access-tickets-manage') ? $q
            : $q->where(fn ($w) => $w->where('assigned_to', $user->id)->orWhere('created_by', $user->id));
    }

    public function number(): string
    {
        return 'TKT-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && $this->due_at && $this->due_at->isPast();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? '#64748b';
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority][0] ?? $this->priority;
    }

    public function priorityColor(): string
    {
        return self::PRIORITIES[$this->priority][1] ?? '#64748b';
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category][0] ?? $this->category;
    }

    public function categoryIcon(): string
    {
        return self::CATEGORIES[$this->category][1] ?? 'fas fa-ticket-alt';
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? '';
    }

    /** Who/where the problem is: customer, else contact, else zone. */
    public function who(): string
    {
        return $this->customer?->name ?? $this->contact_name ?? $this->zone ?? '—';
    }

    public function canBeWorkedBy(User $user): bool
    {
        return $user->can('access-tickets-manage') || $this->assigned_to === $user->id || $this->created_by === $user->id;
    }

    protected function activityLogTitle(): string
    {
        return $this->number() . ' ' . $this->subject;
    }
}
