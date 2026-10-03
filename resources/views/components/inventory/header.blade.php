@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null])

<x-module-header :title="$title" :subtitle="$subtitle" :icon="$icon" :back="$back" :tabs="[
    ['inventory.summary', 'inventory.summary', 'Summary', 'fas fa-chart-pie', 'access-inventory-summary'],
    ['inventory.items.index', 'inventory.items.*', 'Products & Stock', 'fas fa-box', 'access-inventory-stock'],
    ['inventory.entries.index', 'inventory.entries.*', 'Stock Entries', 'fas fa-exchange-alt', 'access-inventory-stock'],
    ['inventory.assets', 'inventory.assets', 'Company Assets', 'fas fa-building', 'access-inventory-assets'],
]">{{ $slot }}</x-module-header>
