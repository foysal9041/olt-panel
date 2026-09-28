<?php

return [

    // How many days of raw probe results to keep. Older rows are pruned
    // daily by the app:prune-latency command.
    'retention_days' => (int) env('LATENCY_RETENTION_DAYS', 30),

    // Gap between consecutive pings inside one probe (seconds). 0.2 is the
    // smallest interval iputils ping allows a non-root user.
    'ping_interval' => 0.2,

    // How many targets are pinged at the same time.
    'concurrency' => (int) env('LATENCY_CONCURRENCY', 25),

    // Graph ranges shown on a target's detail page, SmokePing style.
    'ranges' => [
        '3h'  => ['label' => 'Last 3 Hours', 'seconds' => 3 * 3600],
        '30h' => ['label' => 'Last 30 Hours', 'seconds' => 30 * 3600],
        '7d'  => ['label' => 'Last 7 Days', 'seconds' => 7 * 86400],
        '30d' => ['label' => 'Last 30 Days', 'seconds' => 30 * 86400],
    ],

];
