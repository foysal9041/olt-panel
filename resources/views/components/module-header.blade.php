@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null, 'tabs' => []])

{{--
    Shared page header for module pages: title row + section tabs.
    $tabs: list of [route name, active pattern, label, icon, ability].
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
                <h1>{{ $title }}</h1>
                @if ($subtitle)
                    <p>{{ $subtitle }}</p>
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
            @can($ability)
                <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}">
                    <i class="{{ $tabIcon }}"></i> <span>{{ $label }}</span>
                </a>
            @endcan
        @endforeach
    </nav>
    @endif
</div>
