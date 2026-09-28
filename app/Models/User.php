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
        'status',
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
