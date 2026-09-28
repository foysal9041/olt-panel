@extends('adminlte::page')

@section('title', 'Telegram')

@section('content_header')
<h1>Telegram Alerts</h1>
<p class="text-muted mb-0">One bot for every alert in the panel: switch ports, switch reachability, transceiver Rx and latency thresholds.</p>
@stop

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">

    <div class="col-lg-7">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-telegram-plane"></i> Alert Settings</h3>
            </div>

            <form method="POST" action="{{ route('settings.telegram.update') }}">
                @csrf
                @method('PUT')

                <div class="card-body">

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @unless ($settings->telegram_enabled)
                        <div class="alert alert-warning py-2">
                            <i class="fas fa-exclamation-triangle"></i> Alerts are <strong>OFF</strong> — nothing is sent to Telegram until you turn this on and save.
                        </div>
                    @endunless

                    <div class="custom-control custom-switch mb-3">
                        <input type="hidden" name="telegram_enabled" value="0">
                        <input type="checkbox" class="custom-control-input" id="telegram_enabled" name="telegram_enabled" value="1"
                               @checked(old('telegram_enabled', $settings->telegram_enabled))>
                        <label class="custom-control-label font-weight-bold" for="telegram_enabled">Send alerts to Telegram</label>
                    </div>

                    <div class="form-group">
                        <label>Bot Token</label>
                        <input type="password" name="telegram_bot_token" class="form-control" autocomplete="new-password"
                               placeholder="{{ $settings->telegram_bot_token ? '•••••••• saved — leave blank to keep' : '123456789:ABCdefGhIJKlmNoPQRstuVWXyz' }}">
                        <small class="form-text text-muted">Stored encrypted.</small>
                    </div>

                    <div class="form-group">
                        <label>Chat ID(s)</label>
                        <input type="text" name="telegram_chat_ids" class="form-control"
                               value="{{ old('telegram_chat_ids', $settings->telegram_chat_ids) }}"
                               placeholder="-1001234567890, 987654321">
                        <small class="form-text text-muted">Group, channel or personal chat IDs, separated by commas.</small>
                    </div>

                    <hr>

                    <label class="d-block">Alert me when…</label>

                    <div class="custom-control custom-checkbox">
                        <input type="hidden" name="alert_port_status" value="0">
                        <input type="checkbox" class="custom-control-input" id="alert_port_status" name="alert_port_status" value="1"
                               @checked(old('alert_port_status', $settings->alert_port_status))>
                        <label class="custom-control-label" for="alert_port_status">A switch port goes UP or DOWN</label>
                    </div>

                    <div class="custom-control custom-checkbox mb-3">
                        <input type="hidden" name="alert_switch_status" value="0">
                        <input type="checkbox" class="custom-control-input" id="alert_switch_status" name="alert_switch_status" value="1"
                               @checked(old('alert_switch_status', $settings->alert_switch_status))>
                        <label class="custom-control-label" for="alert_switch_status">A switch stops / starts answering SNMP</label>
                    </div>

                    <p class="small text-muted mb-3">
                        <i class="fas fa-info-circle"></i>
                        Latency alerts are set per target in
                        @can('access-latency-targets')
                            <a href="{{ route('latency.targets.index') }}">Latency Checker → Manage Targets</a>.
                        @else
                            Latency Checker → Manage Targets.
                        @endcan
                        Individual switches and ports can be muted from their own pages.
                    </p>

                    <div class="form-group mb-0">
                        <label>Fallback low Rx threshold (dBm) <small class="text-muted">(optional)</small></label>
                        <input type="number" step="0.1" name="rx_low_threshold" class="form-control" style="max-width: 12rem"
                               value="{{ old('rx_low_threshold', $settings->rx_low_threshold) }}" placeholder="e.g. -25">
                        <small class="form-text text-muted">
                            Rx alerts use each module's own <strong>low warning</strong> limit read from the switch (Cisco).
                            This value is only used for modules that don't report one (MikroTik, Huawei, …). Leave blank to turn those off.
                        </small>
                    </div>

                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Test</h3>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Sends a test message to every chat ID using the saved settings.</p>
                <form method="POST" action="{{ route('settings.telegram.test') }}">
                    @csrf
                    <button class="btn btn-info" {{ $settings->telegram_bot_token && $settings->chatIds() ? '' : 'disabled' }}>
                        <i class="fab fa-telegram-plane"></i> Send Test Message
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">How to set up</h3>
            </div>
            <div class="card-body small">
                <ol class="pl-3 mb-0">
                    <li class="mb-2">In Telegram, open <strong>@BotFather</strong>, send <code>/newbot</code> and follow the steps. Copy the <strong>bot token</strong> it gives you.</li>
                    <li class="mb-2">Add the bot to your NOC group (or open a chat with it) and send any message.</li>
                    <li class="mb-2">Open <code>https://api.telegram.org/bot&lt;TOKEN&gt;/getUpdates</code> in a browser and copy the <code>"chat":{"id": …}</code> number. Group IDs start with <code>-100</code>.</li>
                    <li>Paste both here, turn alerts on, save, then click <strong>Send Test Message</strong>.</li>
                </ol>
            </div>
        </div>
    </div>

</div>

@stop
