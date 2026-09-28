@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null])

<x-module-header :title="$title" :subtitle="$subtitle" :icon="$icon" :back="$back" :tabs="[
    ['olt.dashboard', 'olt.dashboard', 'Overview', 'fas fa-tachometer-alt', 'access-olt-dashboard'],
    ['olt.index', ['olt.index', 'olt.create', 'olt.edit', 'olt.show'], 'OLTs', 'fas fa-network-wired', 'access-olt-manage'],
    ['switches.index', 'switches.*', 'Switches', 'fas fa-server', 'access-olt-switches'],
    ['switch-events.index', 'switch-events.*', 'Events', 'fas fa-history', 'access-olt-switches'],
    ['latency.index', 'latency.*', 'Latency', 'fas fa-wave-square', 'access-latency-graphs'],
    ['zones.index', 'zones.*', 'Zones', 'fas fa-map-marked-alt', 'access-olt-zones'],
    ['vlans.index', 'vlans.*', 'VLANs', 'fas fa-stream', 'access-olt-vlans'],
    ['ip-pools.index', 'ip-pools.*', 'IP', 'fas fa-globe', 'access-olt-ip'],
    ['nttn-links.index', 'nttn-links.*', 'NTTN', 'fas fa-project-diagram', 'access-olt-nttn'],
    ['support-contacts.index', 'support-contacts.*', 'Support', 'fas fa-headset', 'access-olt-support'],
]">
    @isset($badge) <x-slot:badge>{{ $badge }}</x-slot:badge> @endisset
    {{ $slot }}
</x-module-header>
