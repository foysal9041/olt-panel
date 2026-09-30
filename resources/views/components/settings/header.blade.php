@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null])

<x-module-header :title="$title" :subtitle="$subtitle" :icon="$icon" :back="$back" :tabs="[
    ['users.index', 'users.*', 'Users', 'fas fa-users', 'access-settings-users'],
    ['activity.index', 'activity.*', 'Activity Log', 'fas fa-history', 'access-settings-users'],
    ['settings.telegram', 'settings.telegram*', 'Telegram Alerts', 'fab fa-telegram-plane', 'access-settings-telegram'],
    ['settings.whatsapp', 'settings.whatsapp*', 'WhatsApp Alerts', 'fab fa-whatsapp', 'access-settings-telegram'],
    ['settings', 'settings', 'General', 'fas fa-sliders-h', 'access-settings-general'],
    ['profile.edit', 'profile.edit', 'My Profile', 'fas fa-user-circle', null],
    ['profile.activity', 'profile.activity', 'My Activity', 'fas fa-user-clock', null],
]">
    @isset($badge) <x-slot:badge>{{ $badge }}</x-slot:badge> @endisset
    {{ $slot }}
</x-module-header>
