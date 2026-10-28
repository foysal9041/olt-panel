@extends('adminlte::page')

@section('title', 'Data Retention')

@use('App\Support\Retention')
@php
    $err = fn ($f) => $errors->has($f) ? ' is-invalid' : '';
    $group = fn ($kind) => str_starts_with($kind, 'graph_') ? 'graph' : $kind;
    $groups = ['graph' => 'Graphs (traffic, CPU, memory, sensors)', 'latency' => 'Latency checks', 'port_readings' => 'Optical Rx/Tx readings', 'port_events' => 'Port events'];
@endphp

@section('content_header')
<x-settings.header title="Data Retention" icon="fas fa-broom"
    subtitle="How long graph and monitoring history is kept before it is deleted automatically" />
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $e)
            <div>{{ $e }}</div>
        @endforeach
    </div>
@endif

<div class="row">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('settings.retention.update') }}" class="card acct-panel">
            @csrf
            @method('PUT')
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history mr-1 text-primary"></i> Keep history for</h3>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="enabled" name="enabled" value="1" @checked(old('enabled', $r['enabled'] ?? true))>
                    <label class="custom-control-label" for="enabled">Auto delete</label>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table mb-0">
                    <tbody>
                        @foreach (Retention::KINDS as $kind => [$label, $help, $default, $min, $max])
                            <tr>
                                <td class="pl-3">
                                    <div class="font-weight-bold">{{ \App\Support\Ui::t($label) }}</div>
                                    <small class="text-muted">{{ \App\Support\Ui::t($help) }}</small>
                                </td>
                                <td style="width: 11rem">
                                    <div class="input-group input-group-sm">
                                        <input type="number" name="{{ $kind }}" min="{{ $min }}" max="{{ $max }}" required class="form-control text-right{{ $err($kind) }}" value="{{ old($kind, Retention::days($kind)) }}">
                                        <div class="input-group-append"><span class="input-group-text">days</span></div>
                                    </div>
                                    <small class="text-muted">{{ $min }}–{{ $max }} · default {{ $default }}</small>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex flex-wrap align-items-center" style="gap:.5rem 1rem">
                <button class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
                <small class="text-muted">Deleted every night at 03:30 while Auto delete is on. Shorter times free disk space but older graphs show gaps; the graphs need at least the minimums shown.</small>
            </div>
        </form>
    </div>

    <div class="col-lg-5">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-database mr-1 text-primary"></i> Disk use now</h3></div>
            <div class="card-body p-0">
                @foreach ($groups as $g => $label)
                    @php $s = $sizes[$g]; @endphp
                    <div class="acct-list-row">
                        <span class="acct-list-main">
                            <div class="acct-list-title">{{ \App\Support\Ui::t($label) }}</div>
                            <div class="acct-list-sub">
                                ~{{ number_format($s['rows']) }} rows
                                @if ($s['oldest'])
                                    · {{ \App\Support\Ui::t('oldest') }} {{ $s['oldest']->format('d M Y') }}
                                @endif
                            </div>
                        </span>
                        <span class="acct-list-amount">{{ $s['mb'] >= 1024 ? number_format($s['mb'] / 1024, 2) . ' GB' : number_format($s['mb'], 1) . ' MB' }}</span>
                    </div>
                @endforeach
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('settings.retention.prune') }}" class="js-confirm-delete" data-confirm-message="Delete everything older than the days set here now?">
                    @csrf
                    <button class="btn btn-outline-danger btn-sm"><i class="fas fa-broom"></i> Delete old data now</button>
                </form>
                <small class="text-muted d-block mt-1">Uses the saved days. A large backlog can take a few minutes.</small>
            </div>
        </div>
    </div>
</div>

@stop
