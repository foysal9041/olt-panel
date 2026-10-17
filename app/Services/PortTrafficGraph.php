<?php

namespace App\Services;

use App\Models\SwitchPort;

/**
 * The "Top interfaces" list for graph dashboards. Graphs themselves are
 * built by GraphEngine.
 */
class PortTrafficGraph
{
    /** A reading older than this (seconds) isn't "current". */
    protected const CURRENT_FOR = 300;

    /**
     * The busiest interfaces right now among $switchIds (all when empty):
     * physical ports and port-channels, by in + out.
     *
     * @param  list<int>  $switchIds
     * @return \Illuminate\Support\Collection<int, SwitchPort>
     */
    public function top(array $switchIds, int $limit)
    {
        return SwitchPort::with('networkSwitch:id,name')
            ->when($switchIds, fn ($q) => $q->whereIn('network_switch_id', $switchIds))
            ->whereNotNull('in_bps')->where('traffic_at', '>=', now()->subSeconds(self::CURRENT_FOR))
            ->orderByRaw('(in_bps + out_bps) DESC')
            ->limit($limit)
            ->get();
    }
}
