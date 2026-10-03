<?php

/*
|--------------------------------------------------------------------------
| Roles
|--------------------------------------------------------------------------
|
| Each user has one role. Admin can do everything (AppServiceProvider's
| Gate::before). For every other role, what the user can open is the module
| access saved for that user; the role's 'permissions' are the starting
| set the user form fills in when the role is picked, so the usual case is
| one click and special cases can still be adjusted per user.
|
| 'permissions': module => '*' (the whole module) or a list of submodules,
| keyed as in config/modules.php.
|
| Behaviour tied to a role elsewhere: viewer and owner are read-only
| (ViewerReadOnly middleware) apart from their own to-dos and leave;
| operator manages users of their own zone.
|
*/

return [
    'admin' => [
        'label' => 'Admin',
        'description' => 'Everything — all modules, users and settings.',
        'icon' => 'fas fa-crown',
        'color' => '#e11d48',
        'permissions' => '*',
    ],
    'accountant' => [
        'label' => 'Accountant',
        'description' => 'Accounts: cash book, income & expenses, billing, settlement, salary, net profit; inventory.',
        'icon' => 'fas fa-calculator',
        'color' => '#d97706',
        'permissions' => ['accounts' => '*', 'inventory' => '*'],
    ],
    'noc' => [
        'label' => 'NOC Engineer',
        'description' => 'Network: OLTs, switches, latency, IP & VLAN, NTTN links; tickets and the equipment stock.',
        'icon' => 'fas fa-satellite-dish',
        'color' => '#0284c7',
        'permissions' => ['olt' => '*', 'latency' => '*', 'tickets' => '*', 'inventory' => ['stock', 'assets'], 'tasks' => ['assign']],
    ],
    'hr' => [
        'label' => 'HR / Attendance',
        'description' => 'Attendance, employees, leave and duty shifts; salary sheet; team tasks.',
        'icon' => 'fas fa-user-clock',
        'color' => '#16a34a',
        'permissions' => ['attendance' => '*', 'accounts' => ['salaries'], 'tasks' => '*'],
    ],
    'operator' => [
        'label' => 'Zone Operator',
        'description' => 'Their own zone: OLT dashboard and records, switches, support contacts.',
        'icon' => 'fas fa-map-marker-alt',
        'color' => '#4f46e5',
        'permissions' => ['olt' => ['dashboard', 'manage', 'switches', 'support']],
    ],
    'owner' => [
        'label' => 'Owner / Partner',
        'description' => 'Read-only summaries: accounts, net profit & shares, company assets & stock, tickets and the team\'s tasks.',
        'icon' => 'fas fa-user-tie',
        'color' => '#0f766e',
        'permissions' => ['accounts' => ['dashboard', 'profit', 'partners'], 'inventory' => ['summary', 'assets'], 'tickets' => '*', 'tasks' => ['board']],
    ],
    'viewer' => [
        'label' => 'Viewer',
        'description' => 'Read only — can look at what they are given, can\'t change anything.',
        'icon' => 'fas fa-eye',
        'color' => '#64748b',
        'permissions' => ['olt' => ['dashboard'], 'accounts' => ['dashboard']],
    ],
    'employee' => [
        'label' => 'Employee',
        'description' => 'Their own dashboard: to-do list, tasks and tickets given to them, leave.',
        'icon' => 'fas fa-user',
        'color' => '#94a3b8',
        'permissions' => [],
    ],
];
