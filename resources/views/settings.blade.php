@extends('adminlte::page')

@section('title', 'Settings')

@php
    $counts = [
        ['OLTs', \App\Models\Olt::count(), 'fas fa-network-wired', '#4f46e5'],
        ['Switches', \App\Models\NetworkSwitch::count(), 'fas fa-server', '#0284c7'],
        ['Zones', \App\Models\Zone::count(), 'fas fa-map-marked-alt', '#16a34a'],
        ['NTTN links', \App\Models\NttnLink::count(), 'fas fa-project-diagram', '#ea580c'],
        ['IP subnets', \App\Models\IpPool::count(), 'fas fa-globe', '#7c3aed'],
        ['Users', \App\Models\User::count(), 'fas fa-users', '#e11d48'],
    ];
    $info = [
        ['Application', config('app.name')],
        ['Address', config('app.url')],
        ['Time zone', config('app.timezone') . ' · ' . now()->format('d M Y, h:i A')],
        ['Laravel', app()->version()],
        ['PHP', PHP_VERSION],
        ['Environment', app()->environment()],
    ];
@endphp

@section('content_header')
<x-settings.header title="Settings" icon="fas fa-cog" subtitle="System information and alerts" />
@stop

@section('content')

<div class="acct-stats">
    @foreach ($counts as [$label, $value, $icon, $color])
        <div class="acct-stat" style="--accent: {{ $color }}">
            <div class="acct-stat-label">{{ $label }} <i class="{{ $icon }}"></i></div>
            <div class="acct-stat-value">{{ number_format($value) }}</div>
        </div>
    @endforeach
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle mr-1 text-primary"></i> System</h3></div>
            <div class="card-body p-0">
                @foreach ($info as [$label, $value])
                    <div class="acct-list-row">
                        <div class="acct-list-main text-muted small">{{ $label }}</div>
                        <div class="font-weight-bold text-right" style="color:#0f172a">{{ $value }}</div>
                    </div>
                @endforeach
                <div class="acct-list-row">
                    <div class="acct-list-main text-muted small">Monitoring</div>
                    <div class="text-right small">
                        OLT status every <strong>30 s</strong> · latency, switches &amp; NTTN every <strong>1 min</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('access-settings-telegram')
        @php
            $alerts = \App\Models\NocAlertSetting::current();
            $channels = [
                ['Telegram', 'fab fa-telegram-plane', '#0284c7', $alerts->telegram_enabled,
                    $alerts->telegram_bot_token && $alerts->chatIds(), count($alerts->chatIds()) . ' ' . \Illuminate\Support\Str::plural('chat', count($alerts->chatIds())), route('settings.telegram')],
                ['WhatsApp', 'fab fa-whatsapp', '#16a34a', $alerts->whatsapp_enabled,
                    $alerts->whatsappReady(), count($alerts->whatsappRecipients()) . ' ' . \Illuminate\Support\Str::plural('number', count($alerts->whatsappRecipients())), route('settings.whatsapp')],
            ];
        @endphp
        <div class="col-lg-6">
            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-bell mr-1 text-primary"></i> Alert channels</h3>
                </div>
                <div class="card-body p-0">
                    @foreach ($channels as [$name, $icon, $color, $on, $ready, $to, $url])
                        <a href="{{ $url }}" class="acct-list-row">
                            <span class="acct-avatar" style="background:{{ $color }}1a;color:{{ $color }}"><i class="{{ $icon }}"></i></span>
                            <span class="acct-list-main">
                                <div class="acct-list-title">{{ $name }}</div>
                                <div class="acct-list-sub">{{ $ready ? 'Sends to ' . $to : 'Not set up yet' }}</div>
                            </span>
                            @if ($on && $ready)
                                <span class="badge badge-success">On</span>
                            @elseif ($on)
                                <span class="badge badge-warning">On — setup incomplete</span>
                            @else
                                <span class="badge badge-secondary">Off</span>
                            @endif
                        </a>
                    @endforeach
                    <div class="acct-list-row">
                        <div class="acct-list-main text-muted small">Sends</div>
                        <div class="small text-right">Switch/port down &amp; up, low Rx, latency over threshold, NTTN link down/up</div>
                    </div>
                </div>
            </div>
        </div>
    @endcan
</div>

@stop
