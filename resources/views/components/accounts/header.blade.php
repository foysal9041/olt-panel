@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null])

{{-- Shared page header for the Accounts module: title row + section tabs. --}}
@php
    $tabs = [
        ['accounts.dashboard', 'accounts.dashboard', 'Dashboard', 'fas fa-chart-pie', 'access-accounts-dashboard'],
        ['accounts.invoices.index', 'accounts.invoices.*', 'Invoices', 'fas fa-file-invoice', 'access-accounts-invoices'],
        ['accounts.transactions.index', 'accounts.transactions.*', 'Income & Expenses', 'fas fa-exchange-alt', 'access-accounts-transactions'],
        ['accounts.customers.index', 'accounts.customers.*', 'Customers', 'fas fa-users', 'access-accounts-customers'],
        ['accounts.products.index', 'accounts.products.*', 'Products', 'fas fa-box', 'access-accounts-products'],
    ];
@endphp

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

    <nav class="acct-tabs">
        @foreach ($tabs as [$route, $pattern, $label, $tabIcon, $ability])
            @can($ability)
                <a href="{{ route($route) }}" class="{{ request()->routeIs($pattern) ? 'active' : '' }}">
                    <i class="{{ $tabIcon }}"></i> <span>{{ $label }}</span>
                </a>
            @endcan
        @endforeach
    </nav>
</div>
