<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwitchPortReading extends Model
{
    public $timestamps = false;

    /** Days of Rx/Tx history kept; older rows are pruned daily. */
    public const RETENTION_DAYS = 90;

    protected $fillable = [
        'switch_port_id',
        'recorded_at',
        'rx_power',
        'tx_power',
        'temperature',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'rx_power' => 'float',
        'tx_power' => 'float',
        'temperature' => 'float',
    ];

    public function port()
    {
        return $this->belongsTo(SwitchPort::class, 'switch_port_id');
    }
}
