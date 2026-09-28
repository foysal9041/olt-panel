<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Support\Str;

/**
 * Records a created/updated/deleted ActivityLog row for every save on the
 * using model, attributed to the currently authenticated user. Nothing is
 * recorded when there's no authenticated actor (seeders, console commands,
 * queued jobs) since there'd be no "who" to log.
 */
trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        static::created(fn ($model) => $model->recordActivity('created'));
        static::updated(fn ($model) => $model->recordActivity('updated'));
        static::deleted(fn ($model) => $model->recordActivity('deleted'));
    }

    /**
     * Attributes that are never surfaced in a logged diff, on any model.
     *
     * @return list<string>
     */
    protected function activityLogExcept(): array
    {
        return ['password', 'remember_token', 'created_at', 'updated_at'];
    }

    /**
     * Human label for this model's type, e.g. "OLT", "Zone". Defaults to
     * the class basename split into words; override per-model to customize.
     */
    protected function activityLogLabel(): string
    {
        return Str::headline(class_basename($this));
    }

    /**
     * Identifying value used in the log description, e.g. a name or code.
     * Falls back through common columns before resorting to the id.
     */
    protected function activityLogTitle(): string
    {
        foreach (['name', 'title', 'vendor_name', 'host', 'ip', 'ip_address', 'subnet', 'link_id', 'invoice_number', 'email', 'username', 'code'] as $field) {
            if (! empty($this->{$field})) {
                return (string) $this->{$field};
            }
        }

        return "#{$this->getKey()}";
    }

    protected function recordActivity(string $action): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $changes = null;

        if ($action === 'updated') {
            $changes = $this->loggableChanges();

            if ($changes === []) {
                return;
            }
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'subject_label' => $this->activityLogLabel(),
            'description' => sprintf(
                '%s %s: %s',
                ucfirst($action),
                $this->activityLogLabel(),
                $this->activityLogTitle()
            ),
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function loggableChanges(): array
    {
        $except = $this->activityLogExcept();
        $diff = [];

        foreach ($this->getChanges() as $key => $new) {
            if (in_array($key, $except, true)) {
                continue;
            }

            $diff[$key] = [
                'old' => $this->getOriginal($key),
                'new' => $new,
            ];
        }

        return $diff;
    }
}
