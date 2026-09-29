@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null])

<x-module-header :title="$title" :subtitle="$subtitle" :icon="$icon" :back="$back" :tabs="[
    ['accounts.dashboard', 'accounts.dashboard', 'Dashboard', 'fas fa-chart-pie', 'access-accounts-dashboard'],
    ['accounts.cashbook.index', 'accounts.cashbook.*', 'Cash Book', 'fas fa-book-open', 'access-accounts-cashbook'],
    ['accounts.payments.create', 'accounts.payments.*', 'Receive Payment', 'fas fa-hand-holding-usd', 'access-accounts-invoices'],
    ['accounts.invoices.index', 'accounts.invoices.*', 'Invoices', 'fas fa-file-invoice', 'access-accounts-invoices'],
    ['accounts.transactions.index', 'accounts.transactions.*', 'Income & Expenses', 'fas fa-exchange-alt', 'access-accounts-transactions'],
    ['accounts.customers.index', 'accounts.customers.*', 'Customers', 'fas fa-users', 'access-accounts-customers'],
    ['accounts.products.index', 'accounts.products.*', 'Products', 'fas fa-box', 'access-accounts-products'],
    ['accounts.reports.ledger', 'accounts.reports.*', 'Monthly Reports', 'fas fa-calendar-alt', 'access-accounts-reports'],
    ['accounts.salaries.index', 'accounts.salaries.*', 'Salary', 'fas fa-money-check-alt', 'access-accounts-salaries'],
]">{{ $slot }}</x-module-header>
