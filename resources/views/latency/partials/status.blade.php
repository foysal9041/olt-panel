@php($labels = ['up' => 'Up', 'degraded' => 'Packet Loss', 'alert' => 'Threshold', 'down' => 'Down', 'unknown' => 'No Data'])
<span class="latency-status latency-status--{{ $status }}">{{ $labels[$status] }}</span>
