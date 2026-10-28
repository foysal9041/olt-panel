@extends('adminlte::page')

@section('title', 'Server & HTTPS')

@php
    $v = $status['values'];
    $ready = $status['ok'] && ($v['managed'] ?? '') === 'yes';
    $host = $v['host'] ?? parse_url($appUrl, PHP_URL_HOST);
    $ips = array_values(array_filter(explode(' ', $v['ips'] ?? '')));
    $isIp = (bool) filter_var($host, FILTER_VALIDATE_IP);
    $sslOn = ($v['ssl'] ?? '') === 'on';
    $err = fn ($f) => $errors->has($f) ? ' is-invalid' : '';
@endphp

@section('content_header')
<x-settings.header title="Server & HTTPS" icon="fas fa-server"
    subtitle="The address this panel is opened on — the server's IP or a domain — and its HTTPS certificate" />
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-1"></i> {{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $e)
            <div>{{ $e }}</div>
        @endforeach
    </div>
@endif

<div class="row">
    <div class="col-lg-5">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle mr-1 text-primary"></i> Now</h3></div>
            <div class="card-body p-0">
                <div class="acct-list-row">
                    <div class="acct-list-main text-muted small">Panel address</div>
                    <div class="font-weight-bold mono text-right"><a href="{{ $v['app_url'] ?? $appUrl }}">{{ $v['app_url'] ?? $appUrl }}</a></div>
                </div>
                <div class="acct-list-row">
                    <div class="acct-list-main text-muted small">HTTPS</div>
                    <div class="text-right">
                        @if ($sslOn)
                            <span class="badge badge-success"><i class="fas fa-lock"></i> On</span>
                            @if (! empty($v['cert_expiry']))
                                <div class="small text-muted">Let's Encrypt, until {{ \Illuminate\Support\Carbon::parse($v['cert_expiry'])->format('d M Y') }} · renews by itself</div>
                            @endif
                        @elseif (str_starts_with($appUrl, 'https://'))
                            <span class="badge badge-success"><i class="fas fa-lock"></i> On</span>
                            <div class="small text-muted">Set up outside the panel</div>
                        @else
                            <span class="badge badge-secondary"><i class="fas fa-lock-open"></i> Off</span>
                        @endif
                    </div>
                </div>
                @if ($ips)
                    <div class="acct-list-row">
                        <div class="acct-list-main text-muted small">This server's IP</div>
                        <div class="mono text-right">{{ implode(', ', $ips) }}</div>
                    </div>
                @endif
                <div class="acct-list-row">
                    <div class="acct-list-main text-muted small">ZKTeco devices</div>
                    <div class="small text-right">Server address: this server's IP · port 80 (plain HTTP keeps working with HTTPS on)</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        @if (! $status['ok'] && in_array($status['error'], ['missing', 'not-allowed'], true))
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-tools mr-1 text-warning"></i> One step on the server first</h3></div>
                <div class="card-body">
                    <p class="mb-2">Changing the address and HTTPS needs the server's small helper, which the installer sets up. Run this once on the server:</p>
                    <pre class="mono bg-light p-2 mb-2">sudo bash {{ base_path('install.sh') }}</pre>
                    <small class="text-muted">It updates the panel and keeps all data and settings. Then reload this page.</small>
                </div>
            </div>
        @elseif (! $status['ok'])
            <div class="alert alert-warning">{{ $status['error'] }}</div>
        @elseif (! $ready)
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-hand-paper mr-1 text-secondary"></i> Managed outside the panel</h3></div>
                <div class="card-body">
                    nginx on this server was set up by hand, so the panel doesn't change its address or certificate. Change them in the server's nginx configuration, and set <code>APP_URL</code> in <code>.env</code>.
                </div>
            </div>
        @else
            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-globe mr-1 text-primary"></i> Panel address</h3></div>
                <form method="POST" action="{{ route('settings.server.address') }}" class="card-body">
                    @csrf
                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" id="mode-ip" name="mode" value="ip" class="custom-control-input" @checked(old('mode', $isIp ? 'ip' : 'domain') === 'ip')>
                        <label class="custom-control-label" for="mode-ip">The server's IP address</label>
                    </div>
                    <div class="pl-4 mb-3">
                        <select name="ip" class="form-control form-control-sm mono{{ $err('ip') }}" style="max-width: 16rem">
                            @foreach (array_unique(array_filter(array_merge($ips, $isIp ? [$host] : []))) as $ip)
                                <option value="{{ $ip }}" @selected(old('ip', $isIp ? $host : '') === $ip)>{{ $ip }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Plain HTTP only — certificates are for domain names.</small>
                    </div>
                    <div class="custom-control custom-radio mb-2">
                        <input type="radio" id="mode-domain" name="mode" value="domain" class="custom-control-input" @checked(old('mode', $isIp ? 'ip' : 'domain') === 'domain')>
                        <label class="custom-control-label" for="mode-domain">A domain name</label>
                    </div>
                    <div class="pl-4 mb-3">
                        <input type="text" name="domain" maxlength="253" class="form-control form-control-sm mono{{ $err('domain') }}" style="max-width: 22rem"
                               value="{{ old('domain', $isIp ? '' : $host) }}" placeholder="noc.example.com">
                        <small class="text-muted">Its DNS A record must point to this server first{{ $ips ? ' (' . $ips[0] . ')' : '' }}.</small>
                    </div>
                    <button class="btn btn-primary btn-sm js-confirm" data-confirm-message="Change the panel's address? You'll open it at the new address afterwards."><i class="fas fa-save"></i> Save address</button>
                </form>
            </div>

            <div class="card acct-panel">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-lock mr-1 text-primary"></i> HTTPS (free certificate from Let's Encrypt)</h3></div>
                <div class="card-body">
                    @if ($sslOn)
                        <p class="mb-2">HTTPS is on for <strong>{{ $host }}</strong>; plain HTTP addresses redirect to it (except the attendance devices').</p>
                        <form method="POST" action="{{ route('settings.server.ssl-off') }}" class="js-confirm-delete" data-confirm-message="Turn HTTPS off? The panel goes back to plain http://.">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm"><i class="fas fa-lock-open"></i> Turn HTTPS off</button>
                        </form>
                    @elseif ($isIp)
                        <p class="text-muted mb-0">Set a domain name as the panel's address first — certificates can't be issued for an IP address.</p>
                    @else
                        <form method="POST" action="{{ route('settings.server.ssl-on') }}">
                            @csrf
                            <div class="form-group mb-2">
                                <label class="small font-weight-bold mb-1">Email for expiry notices</label>
                                <input type="email" name="email" maxlength="254" required class="form-control form-control-sm{{ $err('email') }}" style="max-width: 22rem" value="{{ old('email', \App\Support\Branding::email()) }}">
                            </div>
                            <small class="text-muted d-block mb-2">
                                {{ $host }} must point to this server and port 80 must be open to the internet. Turning it on means accepting the Let's Encrypt terms. It takes up to a minute.
                            </small>
                            <button class="btn btn-success btn-sm js-busy"><i class="fas fa-lock"></i> Turn HTTPS on</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@stop

@section('js')
<script>
    // Address change: confirm first; HTTPS: show it's working while the certificate is fetched.
    document.querySelectorAll('.js-confirm').forEach(function (b) {
        b.addEventListener('click', function (e) { if (!confirm(b.getAttribute('data-confirm-message'))) e.preventDefault(); });
    });
    document.querySelectorAll('.js-busy').forEach(function (b) {
        b.form.addEventListener('submit', function () { b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + uiText('Getting the certificate…', 'সার্টিফিকেট আনা হচ্ছে…'); });
    });
</script>
@stop
