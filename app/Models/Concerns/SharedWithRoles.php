<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Something a user builds and shares (graph dashboards, weathermaps): its
 * owner and admins always open it; shared with "everyone" or with chosen
 * roles (view, or view and edit). Only the owner and admins change its
 * settings. The model needs user_id, visibility and a roles() relation
 * whose rows have role and can_edit.
 */
trait SharedWithRoles
{
    public const VISIBILITY = [
        'private' => 'Only me',
        'roles' => 'Chosen roles',
        'everyone' => 'Everyone with access to this module',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** What $user can open. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('user_id', $user->id)
            ->orWhere('visibility', 'everyone')
            ->orWhere(fn ($q) => $q->where('visibility', 'roles')
                ->whereHas('roles', fn ($r) => $r->where('role', strtolower((string) $user->role)))));
    }

    public function canView(User $user): bool
    {
        return $user->isAdmin()
            || $this->user_id === $user->id
            || $this->visibility === 'everyone'
            || ($this->visibility === 'roles' && $this->roleRow($user) !== null);
    }

    /** Change what's on it (graphs, nodes, links). */
    public function canEdit(User $user): bool
    {
        if ($user->isViewer()) {
            return false;
        }

        return $this->canManage($user) || ($this->visibility === 'roles' && (bool) $this->roleRow($user)?->can_edit);
    }

    /** Rename, share or delete it: the owner and admins. */
    public function canManage(User $user): bool
    {
        return $user->isAdmin() || ($this->user_id === $user->id && ! $user->isViewer());
    }

    protected function roleRow(User $user)
    {
        return $this->roles->firstWhere('role', strtolower((string) $user->role));
    }

    /** "NOC Engineer, Zone Operator (edit)" / "Only me" / "Everyone …". */
    public function sharingLabel(): string
    {
        if ($this->visibility !== 'roles') {
            return self::VISIBILITY[$this->visibility] ?? self::VISIBILITY['private'];
        }
        $roles = config('roles');

        return $this->roles->map(fn ($r) => ($roles[$r->role]['label'] ?? ucfirst($r->role)) . ($r->can_edit ? ' (edit)' : ''))->implode(', ') ?: self::VISIBILITY['private'];
    }

    /**
     * Save who it is shared with. $levels: role => none | view | edit.
     *
     * @param  array<string, string>  $levels
     */
    public function syncShares(string $visibility, array $levels): void
    {
        $this->roles()->delete();
        if ($visibility !== 'roles') {
            return;
        }
        foreach (array_intersect_key($levels, config('roles')) as $role => $level) {
            if (in_array($level, ['view', 'edit'], true) && $role !== 'admin') {
                $this->roles()->create(['role' => $role, 'can_edit' => $level === 'edit' && ! in_array($role, ['viewer', 'owner'], true)]);
            }
        }
    }
}
