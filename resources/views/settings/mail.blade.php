@extends('adminlte::page')

@section('title', 'Email')

@php
    $complete = $settings->complete();
    $err = fn ($f) => $errors->has($f) ? ' is-invalid' : '';
    $statusBadge = ['sent' => 'badge-success', 'failed' => 'badge-danger', 'sending' => 'badge-warning'];
@endphp

@section('content_header')
<x-settings.header title="Email" icon="fas fa-envelope"
    subtitle="The office mailbox that invoices and money receipts are emailed from, as PDFs" />
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">

    <div class="col-lg-7">
        <div class="card acct-panel">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-envelope mr-1 text-primary"></i> Outgoing mail (SMTP)</h3>
                @if ($settings->ready())
                    <span class="badge badge-success">On</span>
                @elseif ($settings->enabled)
                    <span class="badge badge-warning">On — setup incomplete</span>
                @else
                    <span class="badge badge-secondary">Off</span>
                @endif
            </div>

            <form method="POST" action="{{ route('settings.mail.update') }}" autocomplete="off">
                @csrf
                @method('PUT')

                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <div class="custom-control custom-switch mb-3">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" class="custom-control-input" id="enabled" name="enabled" value="1" @checked(old('enabled', $settings->enabled))>
                        <label class="custom-control-label font-weight-bold" for="enabled">Email invoices and money receipts to clients</label>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>Mail server (SMTP host)</label>
                            <input type="text" name="host" class="form-control mono{{ $err('host') }}" value="{{ old('host', $settings->host) }}" placeholder="mail.sunlitnetwork.com">
                        </div>
                        <div class="col-7 col-md-4 form-group">
                            <label>Encryption</label>
                            <select name="encryption" id="mail-enc" class="form-control">
                                @foreach (\App\Models\MailSetting::ENCRYPTIONS as $key => $enc)
                                    <option value="{{ $key }}" data-port="{{ $enc['port'] }}" @selected(old('encryption', $settings->encryption) === $key)>{{ $enc['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-5 col-md-2 form-group">
                            <label>Port</label>
                            <input type="number" name="port" id="mail-port" min="1" max="65535" class="form-control mono{{ $err('port') }}" value="{{ old('port', $settings->port) }}" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control{{ $err('username') }}" value="{{ old('username', $settings->username) }}" placeholder="billing@sunlitnetwork.com" autocomplete="off">
                            <small class="form-text text-muted">Usually the full email address of the mailbox.</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control{{ $err('password') }}" autocomplete="new-password"
                                   placeholder="{{ $settings->password ? '•••••••• saved — leave blank to keep' : 'mailbox password' }}">
                            <small class="form-text text-muted">Stored encrypted; never shown or logged.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>From address</label>
                            <input type="email" name="from_address" class="form-control{{ $err('from_address') }}" value="{{ old('from_address', $settings->from_address) }}" placeholder="billing@sunlitnetwork.com">
                            <small class="form-text text-muted">Normally the same as the username.</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>From name</label>
                            <input type="text" name="from_name" maxlength="255" class="form-control{{ $err('from_name') }}" value="{{ old('from_name', $settings->from_name ?: 'Sunlit Network DC') }}">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>Replies go to <small class="text-muted">(optional)</small></label>
                            <input type="email" name="reply_to" class="form-control{{ $err('reply_to') }}" value="{{ old('reply_to', $settings->reply_to) }}" placeholder="accounts@sunlitnetwork.com">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Office copy (BCC) <small class="text-muted">(optional)</small></label>
                            <input type="text" name="bcc" maxlength="500" class="form-control{{ $err('bcc') }}" value="{{ old('bcc', $settings->bcc) }}" placeholder="accounts@sunlitnetwork.com">
                            <small class="form-text text-muted">Gets a hidden copy of every mail sent to clients.</small>
                        </div>
                    </div>

                    <hr>
                    <p class="small text-muted mb-0">
                        <i class="fas fa-info-circle"></i> Each client's address is set on their customer page (<em>Email</em>, several separated by commas).
                        Then tick <em>Email to client</em> when making invoices or recording a payment, or use the <i class="fas fa-envelope"></i> buttons on the invoice and settlement pages.
                    </p>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-paper-plane mr-1 text-primary"></i> Test</h3></div>
            <div class="card-body">
                <p class="text-muted small mb-3">Sends a short test mail with the saved settings — even while emailing is off.</p>
                <form method="POST" action="{{ route('settings.mail.test') }}">
                    @csrf
                    <div class="input-group">
                        <input type="email" name="test_to" class="form-control{{ $err('test_to') }}" value="{{ old('test_to', auth()->user()->email) }}" placeholder="you@example.com" required>
                        <div class="input-group-append">
                            <button class="btn btn-success" @disabled(! $complete)><i class="fas fa-paper-plane"></i> Send test</button>
                        </div>
                    </div>
                    @unless ($complete)
                        <small class="d-block text-muted mt-2">Save the mail server, username, password and From address first.</small>
                    @endunless
                </form>
            </div>
        </div>

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-1 text-primary"></i> Recent mails</h3></div>
            <div class="card-body p-0">
                @forelse ($recent as $m)
                    <div class="acct-list-row py-2">
                        <span class="acct-list-main">
                            <div class="acct-list-title" style="font-size:.86rem">{{ \Illuminate\Support\Str::limit($m->subject, 70) }}</div>
                            <div class="acct-list-sub">
                                {{ $m->to ?: '—' }} · {{ $m->created_at->format('d M, h:i A') }}{{ $m->user ? ' · ' . $m->user->name : '' }}
                                @if ($m->failed() && $m->error)<div class="text-danger">{{ \Illuminate\Support\Str::limit($m->error, 160) }}</div>@endif
                            </div>
                        </span>
                        <span class="badge {{ $statusBadge[$m->status] ?? 'badge-light' }}">{{ ucfirst($m->status) }}</span>
                    </div>
                @empty
                    <div class="acct-empty py-3"><i class="fas fa-envelope-open"></i>No mail sent yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="card acct-panel">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-list-ol mr-1 text-primary"></i> How to set up (one time)</h3></div>
    <div class="card-body small">
        <ol class="pl-3 mb-0">
            <li class="mb-2"><strong>Make a mailbox</strong> on the hosting where sunlitnetwork.com's email lives (cPanel → <em>Email Accounts</em> → Create), e.g. <code>billing@sunlitnetwork.com</code>.</li>
            <li class="mb-2"><strong>Find the SMTP details:</strong> cPanel → Email Accounts → <em>Connect Devices</em> shows the outgoing server (often <code>mail.sunlitnetwork.com</code>) with port <strong>465 (SSL)</strong>. Use those here.</li>
            <li class="mb-2"><strong>Cloudflare:</strong> the mail host's DNS record must be <em>DNS only</em> (grey cloud) — Cloudflare's proxy doesn't carry mail.</li>
            <li class="mb-2"><strong>Avoid the spam folder:</strong> in the domain's DNS keep the SPF record (<code>v=spf1 … include:…</code>) and DKIM that the hosting gives you; cPanel → <em>Email Deliverability</em> shows if anything is missing.</li>
            <li><strong>Here:</strong> fill in the form, turn <em>Email invoices and money receipts</em> on, Save, then <em>Send test</em> to your own address.</li>
        </ol>
    </div>
</div>

<script>
(function () {
    // Picking the encryption fills its usual port.
    var enc = document.getElementById('mail-enc'), port = document.getElementById('mail-port');
    enc.addEventListener('change', function () { port.value = enc.selectedOptions[0].dataset.port; });
})();
</script>

@stop
