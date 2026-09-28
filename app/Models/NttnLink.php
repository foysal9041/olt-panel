<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class NttnLink extends Model
{
    use LogsActivity;

    protected $fillable = [
        'link_id',
        'provider',
        'address',
        'bandwidth',
        'public_ip_subnet',
        'private_ip_subnet',
        'peering_ip',
        'ping_ip',
        'monitor',
        'peering_vlan',
        'asn',
        'location',
        'zone',
        'status',
        'remarks',
    ];

    protected $casts = [
        'last_ping_at' => 'datetime',
        'last_ping_ok' => 'boolean',
        'last_ping_rtt' => 'float',
        'last_ping_loss' => 'float',
        'monitor' => 'boolean',
        'link_state' => 'integer',
        'state_changed_at' => 'datetime',
    ];

    /** Checks in a row that must agree before the link is called up/down. */
    public const STATE_AFTER = 2;

    /**
     * Ping results are operational state, not edits worth logging.
     */
    protected function activityLogExcept(): array
    {
        return [
            'created_at', 'updated_at', 'last_ping_at', 'last_ping_ok', 'last_ping_rtt', 'last_ping_loss',
            'link_state', 'state_streak', 'state_changed_at',
        ];
    }

    /**
     * The address to ping: the explicit Ping IP, else the first IP found
     * in the peering IP field (which may be written as "10.0.0.1/30").
     */
    public function pingTarget(): ?string
    {
        if ($this->ping_ip) {
            return $this->ping_ip;
        }

        foreach (preg_split('/[\s,;\/]+/', (string) $this->peering_ip) as $part) {
            if (filter_var($part, FILTER_VALIDATE_IP)) {
                return $part;
            }
        }

        return null;
    }
}
