<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LatencyProbe extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'latency_target_id',
        'probed_at',
        'sent',
        'received',
        'median',
        'min',
        'max',
        'rtts',
    ];

    protected $casts = [
        'probed_at' => 'datetime',
        'rtts' => 'array',
    ];

    public function target()
    {
        return $this->belongsTo(LatencyTarget::class, 'latency_target_id');
    }
}
