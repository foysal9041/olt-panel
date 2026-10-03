<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'zone',
        'olt_access',
        'switch_access',
        'status',
        'employee_id',
    ];

    /** Device access modes for OLTs and switches. */
    public const DEVICE_ACCESS = [
        'all' => 'All devices',
        'zone' => 'Only their zone',
        'selected' => 'Selected devices',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** The HR employee record this login belongs to (for leave). */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /** Tasks given to this user (their own to-dos included). */
    public function tasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    /** Short initials for avatars: "Foysal Ahmed" → "FA". */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim((string) $this->name)) ?: [];
        $letters = array_map(fn ($w) => mb_substr($w, 0, 1), array_slice(array_filter($words), 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: '?';
    }

    public function modulePermissions()
    {
        return $this->hasMany(UserModulePermission::class);
    }

    /**
     * Every action this user has performed (logins/logouts and
     * created/updated/deleted across all logged modules), newest first.
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    /**
     * Whether this user can access the given module (or, when $submodule is
     * given, that specific submodule within it). Admins bypass this entirely
     * via the Gate::before hook in AppServiceProvider — this method is only
     * ever consulted for non-admin users.
     *
     * A module-level row (submodule === '') grants every submodule within
     * that module. Passing no $submodule checks whether the user has any
     * foothold in the module at all — used for the module's outer route
     * group and top-level sidebar entry.
     */
    public function isAdmin(): bool
    {
        return strtolower((string) $this->role) === 'admin';
    }

    /** View-only account (viewer, owner): sees what it's allowed to, changes nothing but its own to-dos and leave. */
    public function isViewer(): bool
    {
        return in_array(strtolower((string) $this->role), ['viewer', 'owner'], true);
    }

    public function allowedOlts()
    {
        return $this->belongsToMany(Olt::class);
    }

    public function allowedSwitches()
    {
        return $this->belongsToMany(NetworkSwitch::class, 'network_switch_user');
    }

    /**
     * Effective access mode for a device type ('olt' or 'switch').
     * Admins always get 'all'; a zone of "all" also means everything.
     */
    public function deviceAccess(string $type): string
    {
        if ($this->isAdmin()) {
            return 'all';
        }

        $mode = $type === 'olt' ? $this->olt_access : $this->switch_access;

        if ($mode === 'zone' && strtolower((string) $this->zone) === 'all') {
            return 'all';
        }

        return array_key_exists($mode, self::DEVICE_ACCESS) ? $mode : 'zone';
    }

    public function canSeeOlt(Olt $olt): bool
    {
        return Olt::visibleTo($this)->whereKey($olt->getKey())->exists();
    }

    public function canSeeSwitch(NetworkSwitch $switch): bool
    {
        return NetworkSwitch::visibleTo($this)->whereKey($switch->getKey())->exists();
    }

    public function hasModuleAccess(string $module, ?string $submodule = null): bool
    {
        if ($submodule === null) {
            return $this->modulePermissions->contains('module', $module);
        }

        return $this->modulePermissions->contains(
            fn (UserModulePermission $p) => $p->module === $module
                && ($p->submodule === '' || $p->submodule === $submodule)
        );
    }

    /**
     * Whether this user has been granted the whole module (every submodule),
     * as opposed to only specific submodules within it.
     */
    public function hasWholeModuleAccess(string $module): bool
    {
        return $this->modulePermissions->contains(
            fn (UserModulePermission $p) => $p->module === $module && $p->submodule === ''
        );
    }

    /**
     * Specific submodules explicitly granted within $module. Meaningless
     * (and empty) if the user has whole-module access — check
     * hasWholeModuleAccess() first.
     *
     * @return list<string>
     */
    public function submoduleKeys(string $module): array
    {
        return $this->modulePermissions
            ->where('module', $module)
            ->pluck('submodule')
            ->filter(fn ($s) => $s !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function moduleKeys(): array
    {
        return $this->modulePermissions->pluck('module')->unique()->values()->all();
    }
}
