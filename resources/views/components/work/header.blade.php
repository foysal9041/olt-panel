@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null])

<x-module-header :title="$title" :subtitle="$subtitle" :icon="$icon" :back="$back" :tabs="[
    ['my.tasks.index', 'my.tasks.*', 'My Tasks & To-do', 'fas fa-check-square', null],
    ['tickets.index', 'tickets.*', 'Tickets', 'fas fa-ticket-alt', null],
    ['my.leave.index', 'my.leave.*', 'My Leave', 'fas fa-umbrella-beach', null],
    ['attendance.leaves.index', 'attendance.leaves.*', 'Leave Approvals', 'fas fa-user-check', 'access-attendance-leaves'],
]">{{ $slot }}</x-module-header>
