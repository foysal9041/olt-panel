@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null, 'tabs' => []])

{{--
    Shared page header for module pages: title row + section tabs.
    $tabs: list of [route name, active pattern(s), label, icon, ability];
    a null ability means every signed-in user sees the tab.
    Optional named slot $badge renders next to the title (e.g. UP/DOWN).
--}}

<div class="acct-header">
    <div class="acct-header-row">
        <div class="acct-header-title">
            @if ($back)
                <a href="{{ $back }}" class="acct-back" title="Back"><i class="fas fa-arrow-left"></i></a>
            @elseif ($icon)
                <span class="acct-header-icon"><i class="{{ $icon }}"></i></span>
            @endif
            <div>
                <h1>{{ \App\Support\Ui::t($title) }} @isset($badge) <span class="acct-header-badge">{{ $badge }}</span> @endisset</h1>
                @if ($subtitle)
                    <p>{{ \App\Support\Ui::t($subtitle) }}</p>
                @endif
            </div>
        </div>

        @if (trim($slot) !== '')
            <div class="acct-header-actions">{{ $slot }}</div>
        @endif
    </div>

    @if ($tabs)
    <nav class="acct-tabs">
        @foreach ($tabs as [$route, $pattern, $label, $tabIcon, $ability])
            @if (! $ability || Gate::allows($ability))
                <a href="{{ route($route) }}" class="{{ request()->routeIs(...(array) $pattern) ? 'active' : '' }}">
                    <i class="{{ $tabIcon }}"></i> <span>{{ \App\Support\Ui::t($label) }}</span>
                </a>
            @endif
        @endforeach
    </nav>
    @endif
</div>
