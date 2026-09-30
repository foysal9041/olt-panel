@extends('adminlte::page')

@section('title', 'WhatsApp Alerts')

@php
    $wa = app(\App\Services\WhatsAppNotifier::class);
    // What a real alert looks like once converted for WhatsApp.
    $sample = "🔴 <b>PORT DOWN</b>\n🖧 <b>Cisco 100G core SW</b> <code>10.10.10.10</code>\n🔌 Port: <b>Ethernet1/29</b> — BAKRA-SW\n📍 Navaron\n🕒 " . now()->format('d M Y, h:i:s A');
    $preview = $settings->whatsapp_mode === 'text'
        ? $wa->toWhatsAppText($sample)
        : '🚨 NOC alert: ' . ($wa->templateParams($wa->toWhatsAppText($sample))[0] ?? '');
    $ready = $settings->whatsappReady();
    $err = fn ($f) => $errors->has($f) ? ' is-invalid' : '';
@endphp

@section('content_header')
<x-settings.header title="WhatsApp Alerts" icon="fab fa-whatsapp"
    subtitle="Send the same NOC alerts to WhatsApp through Meta's WhatsApp Business Cloud API" />
@stop

@section('css')
<style>
    .wa-bubble { position: relative; max-width: 26rem; padding: .6rem .8rem .9rem; border-radius: .6rem .6rem .6rem 0;
        background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.12); font-size: .88rem; white-space: pre-wrap; word-break: break-word; color: #111b21; }
    .wa-bubble small { position: absolute; right: .55rem; bottom: .25rem; font-size: .66rem; color: #667781; }
    .wa-chat { padding: 1rem; border-radius: .6rem; background: #efeae2; }
    .wa-steps li { margin-bottom: .6rem; }
    .wa-steps code { white-space: nowrap; }
</style>
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
                <h3 class="card-title"><i class="fab fa-whatsapp mr-1" style="color:#25d366"></i> WhatsApp settings</h3>
                @if ($settings->whatsapp_enabled && $ready)
                    <span class="badge badge-success">On</span>
                @elseif ($settings->whatsapp_enabled)
                    <span class="badge badge-warning">On — setup incomplete</span>
                @else
                    <span class="badge badge-secondary">Off</span>
                @endif
            </div>

            <form method="POST" action="{{ route('settings.whatsapp.update') }}" autocomplete="off">
                @csrf
                @method('PUT')

                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <div class="custom-control custom-switch mb-3">
                        <input type="hidden" name="whatsapp_enabled" value="0">
                        <input type="checkbox" class="custom-control-input" id="whatsapp_enabled" name="whatsapp_enabled" value="1"
                               @checked(old('whatsapp_enabled', $settings->whatsapp_enabled))>
                        <label class="custom-control-label font-weight-bold" for="whatsapp_enabled">Send alerts to WhatsApp</label>
                    </div>

                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>Phone Number ID</label>
                            <input type="text" name="whatsapp_phone_number_id" class="form-control mono{{ $err('whatsapp_phone_number_id') }}"
                                   value="{{ old('whatsapp_phone_number_id', $settings->whatsapp_phone_number_id) }}" placeholder="e.g. 106540352242922" inputmode="numeric">
                            <small class="form-text text-muted">Meta → WhatsApp → API Setup (not the phone number itself).</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Access token</label>
                            <input type="password" name="whatsapp_token" class="form-control{{ $err('whatsapp_token') }}" autocomplete="new-password"
                                   placeholder="{{ $settings->whatsapp_token ? '•••••••• saved — leave blank to keep' : 'EAAG…' }}">
                            <small class="form-text text-muted">Stored encrypted; never shown or logged.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Send to (WhatsApp numbers)</label>
                        <input type="text" name="whatsapp_recipients" class="form-control mono{{ $err('whatsapp_recipients') }}"
                               value="{{ old('whatsapp_recipients', $settings->whatsapp_recipients) }}" placeholder="8801711000000, 8801811000000">
                        <small class="form-text text-muted">With country code (880…), separated by commas. Each number gets every alert.</small>
                    </div>

                    <div class="form-row">
                        <div class="col-md-4 form-group">
                            <label>Message type</label>
                            <select name="whatsapp_mode" class="form-control" id="wa-mode">
                                <option value="template" @selected(old('whatsapp_mode', $settings->whatsapp_mode) === 'template')>Template (recommended)</option>
                                <option value="text" @selected(old('whatsapp_mode', $settings->whatsapp_mode) === 'text')>Plain text</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group wa-template">
                            <label>Template name</label>
                            <input type="text" name="whatsapp_template" class="form-control mono{{ $err('whatsapp_template') }}"
                                   value="{{ old('whatsapp_template', $settings->whatsapp_template) }}" placeholder="noc_alert">
                        </div>
                        <div class="col-6 col-md-2 form-group wa-template">
                            <label>Language</label>
                            <input type="text" name="whatsapp_template_lang" class="form-control mono{{ $err('whatsapp_template_lang') }}"
                                   value="{{ old('whatsapp_template_lang', $settings->whatsapp_template_lang ?: 'en') }}" placeholder="en">
                        </div>
                        <div class="col-6 col-md-2 form-group">
                            <label>API version</label>
                            <input type="text" name="whatsapp_api_version" class="form-control mono{{ $err('whatsapp_api_version') }}"
                                   value="{{ old('whatsapp_api_version', $settings->whatsapp_api_version ?: 'v23.0') }}" placeholder="v23.0">
                        </div>
                    </div>
                    <p class="small text-muted mb-0" id="wa-mode-help">
                        <strong>Template:</strong> works any time; needs a Meta-approved template whose body has one variable, e.g.
                        <code>🚨 NOC alert: @{{1}}</code>. <strong>Plain text:</strong> only delivered to people who messaged your
                        business number in the last 24 hours — fine for testing, not for alerts.
                    </p>

                    <hr>
                    <p class="small text-muted mb-0">
                        <i class="fas fa-info-circle"></i> Which alerts are sent (ports, switches, NTTN, Rx levels) is set once for both
                        channels in <a href="{{ route('settings.telegram') }}">Telegram Alerts</a>; latency alerts per target.
                        Switches and ports muted there are muted here too.
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
                <p class="text-muted small mb-3">Sends a test alert to every number above using the saved settings — even while alerts are off.</p>
                <form method="POST" action="{{ route('settings.whatsapp.test') }}">
                    @csrf
                    <button class="btn btn-success" @disabled(! $ready)><i class="fab fa-whatsapp"></i> Send test message</button>
                    @unless ($ready)
                        <small class="d-block text-muted mt-2">Save the Phone Number ID, token, a recipient{{ $settings->whatsapp_mode === 'text' ? '' : ' and the template name' }} first.</small>
                    @endunless
                </form>
            </div>
        </div>

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-eye mr-1 text-primary"></i> How an alert looks</h3></div>
            <div class="card-body">
                <div class="wa-chat"><div class="wa-bubble">{{ $preview }}<small>{{ now()->format('h:i A') }}</small></div></div>
                @if ($settings->whatsapp_mode !== 'text')
                    <small class="text-muted d-block mt-2">WhatsApp templates can't hold line breaks, so each alert is one line; several alerts at once are joined with ‖.</small>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card acct-panel">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-list-ol mr-1 text-primary"></i> How to set up (one time, in Meta)</h3></div>
    <div class="card-body small">
        <ol class="wa-steps pl-3 mb-0">
            <li><strong>Create the app:</strong> <code>developers.facebook.com</code> → My Apps → Create App → type <em>Business</em> → add the <strong>WhatsApp</strong> product and link your Meta Business account.</li>
            <li><strong>Add a phone number:</strong> WhatsApp → <em>API Setup</em> → add and verify a number that is <u>not</u> in use on the normal WhatsApp app. Copy its <strong>Phone Number ID</strong> into the form above.
                (Meta's free test number also works, but then every recipient must be added to its allowed list.)</li>
            <li><strong>Permanent token:</strong> <code>business.facebook.com</code> → Settings → Users → <em>System users</em> → add an Admin system user → <em>Assign assets</em> (the app + your WhatsApp account, full control) →
                <em>Generate token</em> with <code>whatsapp_business_messaging</code> and <code>whatsapp_business_management</code>. Paste it above. (The 24-hour token on API Setup is fine for a first test.)</li>
            <li><strong>Template:</strong> WhatsApp Manager → <em>Message templates</em> → Create → category <strong>Utility</strong>, name <code>noc_alert</code>, language English,
                body <code>🚨 NOC alert: @{{1}}</code> (sample: <code>PORT DOWN · Core SW · Ethernet1/29</code>) → Submit. Approval usually takes minutes to a few hours.</li>
            <li><strong>Payment:</strong> add a payment method in Meta Business Settings — Meta charges per message sent (see Meta's WhatsApp pricing for Bangladesh).</li>
            <li><strong>Here:</strong> fill in the form, turn <em>Send alerts to WhatsApp</em> on, Save, then <em>Send test message</em>.</li>
            <li class="text-muted"><strong>Quick first test</strong> (before your own template is approved): use the test number's Phone Number ID and the temporary token from API Setup,
                template <code>hello_world</code>, language <code>en_US</code>, and your own number added to the test number's allowed list. It sends Meta's "Hello World" message.</li>
        </ol>
    </div>
</div>

<script>
(function () {
    var mode = document.getElementById('wa-mode');
    function sync() {
        document.querySelectorAll('.wa-template').forEach(function (el) { el.style.opacity = mode.value === 'template' ? 1 : .45; });
    }
    mode.addEventListener('change', sync);
    sync();
})();
</script>

@stop
