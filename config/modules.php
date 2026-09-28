<?php

/*
|--------------------------------------------------------------------------
| ERP Modules
|--------------------------------------------------------------------------
|
| The fixed list of modules that can be individually granted to a user.
| Admins always have access to everything (see AppServiceProvider's
| Gate::before bypass) regardless of what's recorded here. Every other
| user only sees/reaches a module if there's a matching row for them in
| the user_module_permissions table.
|
| Each module may declare 'submodules' — a finer-grained breakdown that
| can be granted individually instead of the whole module at once. A user
| can be granted the whole module (full access to every submodule) or one
| or more specific submodules. See User::hasModuleAccess().
|
| Dashboard and Profile are intentionally not modules — every logged-in
| user can see their own dashboard/profile.
|
*/

return [
    'olt' => [
        'label' => 'NOC',
        'icon' => 'fas fa-network-wired',
        'submodules' => [
            'dashboard' => 'OLT Dashboard',
            'manage'    => 'OLT Records (List / Add / Edit)',
            'zones'     => 'Zones',
            'vlans'     => 'VLAN Management',
            'ip'        => 'IP Management',
            'nttn'      => 'NTTN Links',
            'support'   => 'Technical Support Contacts',
            'switches'  => 'Switches (SNMP) & Port Events',
        ],
    ],
    'latency' => [
        'label' => 'Latency Checker',
        'icon' => 'fas fa-wave-square',
        'submodules' => [
            'graphs'  => 'Latency Graphs',
            'targets' => 'Manage Targets',
        ],
    ],
    'attendance' => [
        'label' => 'Attendance',
        'icon' => 'fas fa-fingerprint',
        'submodules' => [
            'dashboard'  => 'Dashboard',
            'report'     => 'Attendance Report',
            'absence'    => 'Absence Report',
            'employees'  => 'Employees',
            'leaves'     => 'Leave Management',
            'shifts'     => 'Duty Shifts',
            'devices'    => 'Devices (F18)',
        ],
    ],
    'accounts' => [
        'label' => 'Accounts',
        'icon' => 'fas fa-file-invoice-dollar',
        'submodules' => [
            'dashboard'     => 'Dashboard',
            'invoices'      => 'Invoices',
            'transactions'  => 'Income & Expenses',
            'customers'     => 'Customers',
            'products'      => 'Products',
            'payments-edit' => 'Edit/Delete Payment Entries',
        ],
    ],
    'settings' => [
        'label' => 'Settings',
        'icon' => 'fas fa-cog',
        'submodules' => [
            'general'  => 'General',
            'users'    => 'User Management',
            'telegram' => 'Telegram Alerts',
        ],
    ],
];
