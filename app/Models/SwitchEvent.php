<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwitchEvent extends Model
{
    public $timestamps = false;

    public const TYPES = [
        'port_down'   => ['Port Down', 'danger'],
        'port_up'     => ['Port Up', 'success'],
        'switch_down' => ['Switch Down', 'danger'],
        'switch_up'   => ['Switch Up', 'success'],
        'rx_low'      => ['Rx Low', 'warning'],
        'rx_normal'   => ['Rx Normal', 'info'],
        'util_high'   => ['High Utilisation', 'warning'],
        'util_normal' => ['Utilisation Normal', 'info'],
    ];

    protected $fillable = [
        'network_switch_id',
        'switch_port_id',
        'type',
        'message',
        'notified',
        'occurred_at',
    ];

    protected $casts = [
        'notified' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    /** The type's label, saying "Router" for a router ("Router Down"). */
    public function label(): string
    {
        $label = self::TYPES[$this->type][0] ?? $this->type;

        return $this->networkSwitch?->isRouter() ? str_replace('Switch', 'Router', $label) : $label;
    }

    public function networkSwitch()
    {
        return $this->belongsTo(NetworkSwitch::class);
    }

    public function port()
    {
        return $this->belongsTo(SwitchPort::class, 'switch_port_id');
    }
}
