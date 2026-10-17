<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A port's traffic in one time bucket, the way Cacti keeps it: every
 * 1-minute rate is added to its 1-minute, 30-minute, 2-hour and 1-day
 * bucket (sum, peak and count), so a bucket's average is in_sum / samples.
 * Rates are bits per second.
 */
class SwitchPortTraffic extends Model
{
    protected $table = 'switch_port_traffic';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    /** Bucket size in minutes => days kept. */
    public const RESOLUTIONS = [1 => 3, 30 => 45, 120 => 400, 1440 => 1100];

    /** Graph periods: key => [title, span in seconds, bucket minutes]. Up to 48 hours uses the 1-minute buckets (kept 3 days). */
    public const GRAPHS = [
        'hour' => ['1 Hour', 3600, 1],
        '6h' => ['6 Hours', 6 * 3600, 1],
        'day' => ['24 Hours', 86400, 1],
        '2day' => ['48 Hours', 2 * 86400, 1],
        'week' => ['Weekly', 7 * 86400, 30],
        'month' => ['Monthly', 31 * 86400, 120],
        'year' => ['Yearly', 365 * 86400, 1440],
    ];

    /** Start of the bucket holding $ts; day buckets start at local midnight. */
    public static function bucket(int $ts, int $minutes): int
    {
        $size = $minutes * 60;
        $offset = $minutes >= 1440 ? now()->utcOffset() * 60 : 0;

        return intdiv($ts + $offset, $size) * $size - $offset;
    }

    public function port()
    {
        return $this->belongsTo(SwitchPort::class, 'switch_port_id');
    }
}
