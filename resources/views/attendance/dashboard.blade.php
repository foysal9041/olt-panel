@extends('adminlte::page')

@section('title', 'Attendance Dashboard')

@section('content_header')
<h1>Employee Attendance Dashboard</h1>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="row">

    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-info"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Total Employees</span>
                <span class="info-box-number">{{ $summary['total'] }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-success"><i class="fas fa-check"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Present Today</span>
                <span class="info-box-number">{{ $summary['present'] }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-warning"><i class="fas fa-clock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Late Today</span>
                <span class="info-box-number">{{ $summary['late'] }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-danger"><i class="fas fa-times"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Absent Today</span>
                <span class="info-box-number">{{ $summary['absent'] }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="info-box">
            <span class="info-box-icon bg-secondary"><i class="fas fa-plane-departure"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">On Leave Today</span>
                <span class="info-box-number">{{ $summary['leave'] ?? 0 }}</span>
            </div>
        </div>
    </div>

</div>

<div class="row">

    <div class="col-lg-7">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">Last 7 Days — Present vs Absent</h3>
            </div>
            <div class="card-body">
                <div style="position: relative; height: 260px;">
                    <canvas id="attendanceTrendChart"></canvas>
                </div>

                <table class="table table-sm table-borderless mt-3 mb-0 text-center">
                    <thead>
                        <tr>
                            <th class="text-left">Date</th>
                            @foreach($trend as $day)
                                <th>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D') }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-left"><span class="badge" style="background:#0ca30c;color:#fff;">Present</span></td>
                            @foreach($trend as $day)
                                <td>{{ $day['present'] }}</td>
                            @endforeach
                        </tr>
                        <tr>
                            <td class="text-left"><span class="badge" style="background:#d03b3b;color:#fff;">Absent</span></td>
                            @foreach($trend as $day)
                                <td>{{ $day['absent'] }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        @if($unmappedCount > 0)
            <div class="card card-outline card-warning mt-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        {{ $unmappedCount }} device PIN(s) could not be matched to an employee
                    </h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>PIN (Device User ID)</th>
                                <th>Device</th>
                                <th>Punches</th>
                                <th>Last Seen</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unmappedPunches as $punch)
                                <tr>
                                    <td><code>{{ $punch->device_user_id }}</code></td>
                                    <td>{{ $punch->zkDevice->name ?? $punch->zkDevice->serial_number ?? 'Unknown device' }}</td>
                                    <td>{{ $punch->punches_count }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($punch->last_punched_at)->format('d M, h:i A') }}</td>
                                    <td>
                                        <a href="{{ route('attendance.employees.create', ['device_user_id' => $punch->device_user_id]) }}"
                                           class="btn btn-xs btn-warning">
                                            Map to Employee
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card card-secondary card-outline">
            <div class="card-header">
                <h3 class="card-title">Recent Punches</h3>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Device</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPunches as $punch)
                            <tr>
                                <td>{{ $punch->employee->name ?? 'Unknown' }}</td>
                                <td>{{ $punch->zkDevice->name ?? $punch->zkDevice->serial_number ?? '-' }}</td>
                                <td>{{ $punch->punched_at->format('d M, h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center">No punches recorded yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

@stop

@section('js')
<script>
new Chart(document.getElementById('attendanceTrendChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: [
            @foreach($trend as $day)
                '{{ \Illuminate\Support\Carbon::parse($day['date'])->format('d M') }}',
            @endforeach
        ],
        datasets: [
            {
                label: 'Present',
                data: [@foreach($trend as $day){{ $day['present'] }},@endforeach],
                borderColor: '#0ca30c',
                backgroundColor: 'rgba(12,163,12,0.08)',
                pointBackgroundColor: '#0ca30c',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.25,
                fill: true,
            },
            {
                label: 'Absent',
                data: [@foreach($trend as $day){{ $day['absent'] }},@endforeach],
                borderColor: '#d03b3b',
                backgroundColor: 'rgba(208,59,59,0.08)',
                pointBackgroundColor: '#d03b3b',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0.25,
                fill: true,
            },
        ],
    },
    options: {
        maintainAspectRatio: false,
        legend: {
            display: true,
            position: 'bottom',
        },
        scales: {
            yAxes: [{
                ticks: { beginAtZero: true, precision: 0 },
                gridLines: { color: '#e1e0d9' },
            }],
            xAxes: [{
                gridLines: { display: false },
            }],
        },
    },
});

setInterval(function () {
    location.reload();
}, 10000);
</script>
@stop
