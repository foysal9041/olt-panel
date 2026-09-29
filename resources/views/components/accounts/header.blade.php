@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null])

<x-module-header :title="$title" :subtitle="$subtitle" :icon="$icon" :back="$back" :tabs="[
    ['accounts.dashboard', 'accounts.dashboard', 'Dashboard', 'fas fa-chart-pie', 'access-accounts-dashboard'],
    ['accounts.payments.create', 'accounts.payments.*', 'Receive Payment', 'fas fa-hand-holding-usd', 'access-accounts-invoices'],
    ['accounts.invoices.index', 'accounts.invoices.*', 'Invoices', 'fas fa-file-invoice', 'access-accounts-invoices'],
    ['accounts.transactions.index', 'accounts.transactions.*', 'Income & Expenses', 'fas fa-exchange-alt', 'access-accounts-transactions'],
    ['accounts.customers.index', 'accounts.customers.*', 'Customers', 'fas fa-users', 'access-accounts-customers'],
    ['accounts.products.index', 'accounts.products.*', 'Products', 'fas fa-box', 'access-accounts-products'],
]">{{ $slot }}</x-module-header>
