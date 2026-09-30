<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    /** action => [label, badge class, icon] */
    public const ACTIONS = [
        'login' => ['Signed in', 'badge-success', 'fas fa-sign-in-alt'],
        'logout' => ['Signed out', 'badge-secondary', 'fas fa-sign-out-alt'],
        'login_failed' => ['Failed sign-in', 'badge-danger', 'fas fa-user-lock'],
        'created' => ['Created', 'badge-info', 'fas fa-plus'],
        'updated' => ['Updated', 'badge-warning', 'fas fa-pen'],
        'deleted' => ['Deleted', 'badge-danger', 'fas fa-trash'],
        'access' => ['Access changed', 'badge-primary', 'fas fa-key'],
    ];

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'description',
        'changes',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record something that isn't a plain model save (sign-ins, access
     * changes …), attributed to $userId or the signed-in user.
     */
    public static function record(string $action, string $description, ?Model $subject = null, ?array $changes = null, ?int $userId = null, bool $orCurrentUser = true): self
    {
        return static::create([
            'user_id' => $userId ?? ($orCurrentUser ? auth()->id() : null),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? \Illuminate\Support\Str::headline(class_basename($subject)) : null,
            'description' => \Illuminate\Support\Str::limit($description, 250, '…'),
            'changes' => $changes,
            'ip_address' => request()?->ip(),
            'user_agent' => \Illuminate\Support\Str::limit((string) request()?->userAgent(), 250, ''),
        ]);
    }

    /**
     * [label, badge class, icon] for this row's action.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public function style(): array
    {
        return self::ACTIONS[$this->action] ?? [ucfirst(str_replace('_', ' ', $this->action)), 'badge-light', 'fas fa-circle'];
    }

    /**
     * "Chrome on Windows" from the stored user agent.
     */
    public function device(): ?string
    {
        $ua = (string) $this->user_agent;
        if ($ua === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        return $browser || $os ? trim(($browser ?? 'Browser') . ($os ? " on {$os}" : '')) : null;
    }
}
