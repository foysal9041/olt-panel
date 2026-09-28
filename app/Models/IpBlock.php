<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\SubnetRange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A parent address range (e.g. 103.161.2.0/24) that subnets are
 * allocated from. Free space is never stored — it's whatever no
 * allocation covers.
 */
class IpBlock extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'cidr', 'type', 'description'];

    public function allocations()
    {
        return $this->hasMany(IpPool::class)->orderBy('network');
    }

    public function range(): array
    {
        return SubnetRange::parse($this->cidr);
    }

    public function size(): int
    {
        [$min, $max] = $this->range();

        return $min === null ? 0 : $max - $min + 1;
    }

    /**
     * Addresses covered by allocations (clipped to the block).
     */
    public function usedCount(?Collection $allocations = null): int
    {
        [$bMin, $bMax] = $this->range();
        $used = 0;

        foreach ($allocations ?? $this->allocations as $a) {
            [$min, $max] = SubnetRange::parse($a->subnet);
            if ($min !== null) {
                $used += max(0, min($max, $bMax) - max($min, $bMin) + 1);
            }
        }

        return $used;
    }

    public function utilization(?Collection $allocations = null): float
    {
        return $this->size() ? round($this->usedCount($allocations) / $this->size() * 100, 1) : 0;
    }

    /**
     * Allocations and free gaps in address order, ready for the table:
     * [['free' => false, 'pool' => IpPool, 'start' => int, 'end' => int], ['free' => true, 'cidr' => '…', ...]]
     */
    public function rows(?Collection $allocations = null): array
    {
        [$bMin, $bMax] = $this->range();
        $cursor = $bMin;
        $rows = [];

        $sorted = ($allocations ?? $this->allocations)->sortBy('network');

        foreach ($sorted as $pool) {
            [$min, $max] = SubnetRange::parse($pool->subnet);
            if ($min === null) {
                continue;
            }

            if ($min > $cursor) {
                foreach (SubnetRange::rangeToCidrs($cursor, $min - 1) as $cidr) {
                    [$fMin, $fMax] = SubnetRange::parse($cidr);
                    $rows[] = ['free' => true, 'cidr' => $cidr, 'start' => $fMin, 'end' => $fMax];
                }
            }

            $rows[] = ['free' => false, 'pool' => $pool, 'start' => $min, 'end' => $max];
            $cursor = max($cursor, $max + 1);
        }

        if ($cursor <= $bMax) {
            foreach (SubnetRange::rangeToCidrs($cursor, $bMax) as $cidr) {
                [$fMin, $fMax] = SubnetRange::parse($cidr);
                $rows[] = ['free' => true, 'cidr' => $cidr, 'start' => $fMin, 'end' => $fMax];
            }
        }

        return $rows;
    }
}
