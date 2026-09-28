<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class LatencyTarget extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'host',
        'group',
        'pings',
        'latency_threshold',
        'loss_threshold',
        'notify',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notify' => 'boolean',
        'latency_threshold' => 'float',
        'loss_threshold' => 'float',
        'alert_active' => 'boolean',
        'alert_since' => 'datetime',
        'pings' => 'integer',
        'last_probed_at' => 'datetime',
        'last_median' => 'float',
        'last_loss' => 'float',
    ];

    /**
     * The probe-result columns change every minute from the scheduler;
     * never surface them in the activity log.
     */
    protected function activityLogExcept(): array
    {
        return [
            'created_at', 'updated_at', 'last_probed_at', 'last_median', 'last_loss',
            'alert_active', 'alert_streak', 'alert_since',
        ];
    }

    public function probes()
    {
        return $this->hasMany(LatencyProbe::class);
    }

    /** Probes in a row that must agree before an alert starts or clears. */
    public const ALERT_AFTER = 2;

    public function hasThresholds(): bool
    {
        return $this->latency_threshold !== null || $this->loss_threshold !== null;
    }

    /**
     * Does one probe result break this target's thresholds? Total loss
     * counts as a latency breach too — no replies is worse than slow ones.
     */
    public function breaches(?float $median, float $loss): bool
    {
        if ($this->loss_threshold !== null && $loss >= $this->loss_threshold) {
            return true;
        }

        if ($this->latency_threshold !== null) {
            return $median === null ? $loss >= 100 : $median > $this->latency_threshold;
        }

        return false;
    }

    /**
     * up / degraded / alert / down / unknown — from the latest probe.
     */
    public function getStatusAttribute(): string
    {
        if (! $this->last_probed_at || $this->last_probed_at->lt(now()->subMinutes(5))) {
            return 'unknown';
        }

        if ($this->last_loss >= 100) {
            return 'down';
        }

        if ($this->alert_active) {
            return 'alert';
        }

        return $this->last_loss > 0 ? 'degraded' : 'up';
    }
}
