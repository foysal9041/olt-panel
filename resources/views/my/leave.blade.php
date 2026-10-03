@extends('adminlte::page')

@section('title', 'My Leave')

@php
    $statusColors = ['pending' => '#d97706', 'approved' => '#16a34a', 'rejected' => '#dc2626'];
@endphp

@section('content_header')
<x-work.header title="My Leave" icon="fas fa-umbrella-beach" subtitle="Apply for leave and see what's approved — HR decides in Leave Management" />
@stop

@section('content')

@include('inventory.partials.alerts')

@unless ($employee)
    <div class="card acct-panel">
        <div class="acct-empty">
            <i class="fas fa-id-badge"></i>
            Your login isn't linked to an employee record yet, so leave can't be applied from here.<br>
            Ask an admin to link it: <b>Settings → Users &amp; Roles → edit your user → Employee record</b>.
        </div>
    </div>
@else
    <div class="acct-stats">
        @foreach ($balances as $b)
            <div class="acct-stat" style="--accent: {{ ['#4f46e5', '#0ea5e9', '#16a34a', '#d97706', '#7c3aed'][$loop->index % 5] }}">
                <div class="acct-stat-label">{{ \App\Support\Ui::t($b['type']->name) }} <i class="fas fa-umbrella-beach"></i></div>
                @if ($b['limited'])
                    <div class="acct-stat-value {{ $b['left'] < 0 ? 'text-danger' : '' }}">{{ $b['left'] }} <small>/ {{ $b['allocated'] }}</small></div>
                    <div class="acct-stat-foot">{{ \App\Support\Ui::t('days left this year') }} · {{ \App\Support\Ui::t('used') }} {{ $b['used'] }}@if ($b['pending']) · {{ \App\Support\Ui::t('waiting') }} {{ $b['pending'] }}@endif</div>
                @else
                    <div class="acct-stat-value">{{ $b['used'] }} <small>{{ \App\Support\Ui::t('days') }}</small></div>
                    <div class="acct-stat-foot">{{ \App\Support\Ui::t('Taken this year — no fixed limit') }}</div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-paper-plane mr-1 text-primary"></i> Apply for leave</h3></div>
                <form method="POST" action="{{ route('my.leave.store') }}" class="card-body viewer-ok">
                    @csrf
                    <div class="form-group">
                        <label>Type of leave</label>
                        <select name="leave_type_id" class="form-control" required>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>{{ \App\Support\Ui::t($type->name) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="col-6 form-group">
                            <label>From</label>
                            <input type="date" name="start_date" id="lv-from" value="{{ old('start_date', now()->addDay()->toDateString()) }}" class="form-control" required>
                        </div>
                        <div class="col-6 form-group">
                            <label>To</label>
                            <input type="date" name="end_date" id="lv-to" value="{{ old('end_date', now()->addDay()->toDateString()) }}" class="form-control" required>
                        </div>
                    </div>
                    <div class="small text-muted mb-3" id="lv-days"></div>
                    <div class="form-group">
                        <label>Reason</label>
                        <textarea name="reason" rows="3" class="form-control" maxlength="1000" required placeholder="e.g. Family program in Khulna">{{ old('reason') }}</textarea>
                    </div>
                    <button class="btn btn-primary btn-block"><i class="fas fa-paper-plane"></i> Send request</button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1 text-primary"></i> My requests</h3><span class="small text-muted">{{ $employee->name }}</span></div>
                <div class="card-body p-0">
                    @forelse ($leaves as $leave)
                        <div class="acct-list-row">
                            <span class="acct-avatar" style="background: color-mix(in srgb, {{ $statusColors[$leave->status] ?? '#64748b' }} 14%, #fff); color: {{ $statusColors[$leave->status] ?? '#64748b' }}">
                                <i class="fas {{ ['pending' => 'fa-hourglass-half', 'approved' => 'fa-check', 'rejected' => 'fa-times'][$leave->status] ?? 'fa-circle' }}"></i>
                            </span>
                            <div class="acct-list-main">
                                <div class="acct-list-title">
                                    {{ $leave->start_date->format('d M') }}{{ $leave->end_date->ne($leave->start_date) ? ' – ' . $leave->end_date->format('d M') : '' }} {{ $leave->end_date->format('Y') }}
                                    · {{ $leave->daysCount() }} {{ \App\Support\Ui::t($leave->daysCount() == 1 ? 'day' : 'days') }}
                                </div>
                                <div class="acct-list-sub">
                                    {{ \App\Support\Ui::t($leave->leaveType?->name) }}@if ($leave->reason) · {{ $leave->reason }}@endif
                                    @if ($leave->approvedBy && $leave->status !== 'pending') · {{ \App\Support\Ui::t($leave->status === 'approved' ? 'approved by' : 'rejected by') }} {{ $leave->approvedBy->name }}@endif
                                </div>
                            </div>
                            <span class="wk-pill" style="--c: {{ $statusColors[$leave->status] ?? '#64748b' }}">{{ \App\Support\Ui::t(ucfirst($leave->status)) }}</span>
                            @if ($leave->status === 'pending')
                                <form method="POST" action="{{ route('my.leave.destroy', $leave) }}" class="js-confirm-delete viewer-ok" data-confirm-message="Cancel this leave request?">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-link btn-sm text-danger p-0" title="Cancel request"><i class="fas fa-times-circle"></i></button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="acct-empty"><i class="fas fa-umbrella-beach"></i>No leave requests yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endunless

@stop

@section('js')
<script>
(function () {
    var days = @json(\App\Support\Ui::t('days'));
    function count() {
        var a = new Date($('#lv-from').val()), b = new Date($('#lv-to').val());
        if (isNaN(a) || isNaN(b)) return;
        var n = Math.round((b - a) / 86400000) + 1;
        $('#lv-days').html(n > 0 ? '<i class="far fa-calendar-alt"></i> ' + n + ' ' + days : '');
        if (n < 1) $('#lv-to').val($('#lv-from').val());
    }
    $('#lv-from, #lv-to').on('change', count);
    count();
})();
</script>
@stop
