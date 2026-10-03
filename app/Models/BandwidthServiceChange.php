<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * From `effective_from` on, the client takes `mbps` of this type at `rate`
 * (rate 0 = stopped). The latest row on or before a day is what applies.
 */
class BandwidthServiceChange extends Model
{
    use LogsActivity;

    protected $fillable = ['customer_id', 'bandwidth_type_id', 'effective_from', 'rate', 'mbps', 'note', 'recorded_by'];

    protected $casts = [
        'effective_from' => 'date',
        'rate' => 'string',
        'mbps' => 'string',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function type()
    {
        return $this->belongsTo(BandwidthType::class, 'bandwidth_type_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function activityLogLabel(): string
    {
        return 'Bandwidth Change';
    }

    protected function activityLogTitle(): string
    {
        return ($this->customer?->name ?? '#' . $this->customer_id) . ' · ' . ($this->type?->name ?? '') . ' from ' . $this->effective_from?->format('d M Y');
    }
}
