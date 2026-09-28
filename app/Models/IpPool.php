<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\SubnetRange;
use Illuminate\Database\Eloquent\Model;

class IpPool extends Model
{
    use LogsActivity;

    protected $fillable = [
        'ip_block_id',
        'subnet',
        'network',
        'device',
        'purpose',
        'private_subnet',
        'gateway',
        'type',
        'zone',
        'vlan',
        'status',
        'description',
    ];

    protected static function booted(): void
    {
        // Keep the integer network address in step for ordering.
        static::saving(function (IpPool $pool) {
            [$min] = SubnetRange::parse((string) $pool->subnet);
            $pool->network = $min;
        });
    }

    public function block()
    {
        return $this->belongsTo(IpBlock::class, 'ip_block_id');
    }

    /**
     * Network, broadcast, usable range and size for display.
     *
     * @return array{network:string, broadcast:string, first:string, last:string, size:int, usable:int, prefix:int}|null
     */
    public function details(): ?array
    {
        [$min, $max] = SubnetRange::parse((string) $this->subnet);

        if ($min === null) {
            return null;
        }

        $size = $max - $min + 1;
        $prefix = SubnetRange::prefix($this->subnet);

        // /31 and /32 have no network/broadcast to set aside (RFC 3021).
        $first = $prefix >= 31 ? $min : $min + 1;
        $last = $prefix >= 31 ? $max : $max - 1;

        return [
            'network' => long2ip($min),
            'broadcast' => long2ip($max),
            'first' => long2ip($first),
            'last' => long2ip($last),
            'size' => $size,
            'usable' => $last - $first + 1,
            'prefix' => $prefix,
        ];
    }
}
