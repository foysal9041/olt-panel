<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A hardware sensor of a switch or router found over SNMP — chassis
 * temperature, fan speed, voltage, current or power. Its value is read each
 * poll from value_oid (raw × multiplier) and graphed as metric "sensor:{id}".
 */
class DeviceSensor extends Model
{
    /** Class => [label, unit shown, graph unit]. */
    public const CLASSES = [
        'temperature' => ['Temperature', '°C', 'celsius'],
        'fan' => ['Fan speed', 'rpm', 'rpm'],
        'voltage' => ['Voltage', 'V', 'volts'],
        'current' => ['Current', 'A', 'amps'],
        'power' => ['Power', 'W', 'watts'],
    ];

    protected $fillable = ['network_switch_id', 'source', 'sensor_index', 'class', 'name', 'unit', 'value_oid', 'multiplier', 'value', 'value_at'];

    protected $casts = ['value' => 'float', 'multiplier' => 'float', 'value_at' => 'datetime'];

    public function device()
    {
        return $this->belongsTo(NetworkSwitch::class, 'network_switch_id');
    }

    public function metric(): string
    {
        return 'sensor:' . $this->id;
    }

    public function formatted(): string
    {
        if ($this->value === null) {
            return '—';
        }
        $digits = in_array($this->class, ['fan', 'power'], true) ? 0 : ($this->class === 'temperature' ? 0 : 2);

        return number_format($this->value, $digits) . ' ' . (self::CLASSES[$this->class][1] ?? $this->unit);
    }
}
