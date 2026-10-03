@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null])

<x-module-header :title="$title" :subtitle="$subtitle" :icon="$icon" :back="$back" :tabs="[
    ['accounts.dashboard', 'accounts.dashboard', 'Dashboard', 'fas fa-chart-pie', 'access-accounts-dashboard'],
    ['accounts.cashbook.index', 'accounts.cashbook.*', 'Cash Book', 'fas fa-book-open', 'access-accounts-cashbook'],
    ['accounts.transactions.index', 'accounts.transactions.*', 'Income & Expenses', 'fas fa-exchange-alt', 'access-accounts-transactions'],
    ['accounts.settlements.index', 'accounts.settlements.*', 'Zone Settlement', 'fas fa-file-excel', 'access-accounts-settlements'],
    ['accounts.billing.index', 'accounts.billing.*', 'Bandwidth Billing', 'fas fa-tachometer-alt', 'access-accounts-billing'],
    ['accounts.profit.index', 'accounts.profit.*', 'Net Profit', 'fas fa-chart-line', 'access-accounts-profit'],
    ['accounts.partners.index', 'accounts.partners.*', 'Partners', 'fas fa-user-tie', 'access-accounts-partners'],
    ['accounts.salaries.index', 'accounts.salaries.*', 'Salary', 'fas fa-money-check-alt', 'access-accounts-salaries'],
    ['accounts.customers.index', 'accounts.customers.*', 'Customers', 'fas fa-users', 'access-accounts-customers'],
]">{{ $slot }}</x-module-header>
