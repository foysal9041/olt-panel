@props(['title', 'subtitle' => null, 'icon' => null, 'back' => null])

<x-module-header :title="$title" :subtitle="$subtitle" :icon="$icon" :back="$back" :tabs="[
    ['attendance.dashboard', 'attendance.dashboard', 'Dashboard', 'fas fa-chart-pie', 'access-attendance-dashboard'],
    ['attendance.report', 'attendance.report*', 'Report', 'fas fa-calendar-check', 'access-attendance-report'],
    ['attendance.absence', 'attendance.absence*', 'Absence', 'fas fa-calendar-times', 'access-attendance-absence'],
    ['attendance.employees.index', 'attendance.employees.*', 'Employees', 'fas fa-id-badge', 'access-attendance-employees'],
    ['attendance.leaves.index', 'attendance.leaves.*', 'Leaves', 'fas fa-plane-departure', 'access-attendance-leaves'],
    ['attendance.shifts.index', 'attendance.shifts.*', 'Shifts', 'fas fa-business-time', 'access-attendance-shifts'],
    ['attendance.devices.index', 'attendance.devices.*', 'Devices', 'fas fa-microchip', 'access-attendance-devices'],
]">{{ $slot }}</x-module-header>
