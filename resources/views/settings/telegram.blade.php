@extends('adminlte::page')

@section('title', 'Telegram')

@section('content_header')
<x-settings.header title="Telegram Alerts" icon="fab fa-telegram-plane"
    subtitle="One bot for every alert: switch ports and reachability, transceiver Rx, latency thresholds and NTTN links — also sent to WhatsApp if that's on" />
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

                    <label class="d-block">Alert me when… <small class="text-muted font-weight-normal">(Telegram and WhatsApp)</small></label>

                    <div class="custom-control custom-checkbox">
                        <input type="hidden" name="alert_port_status" value="0">
                        <input type="checkbox" class="custom-control-input" id="alert_port_status" name="alert_port_status" value="1"
                               @checked(old('alert_port_status', $settings->alert_port_status))>
                        <label class="custom-control-label" for="alert_port_status">A switch port goes UP or DOWN</label>
                    </div>

                    <div class="custom-control custom-checkbox">
                        <input type="hidden" name="alert_switch_status" value="0">
                        <input type="checkbox" class="custom-control-input" id="alert_switch_status" name="alert_switch_status" value="1"
                               @checked(old('alert_switch_status', $settings->alert_switch_status))>
                        <label class="custom-control-label" for="alert_switch_status">A switch stops / starts answering SNMP</label>
                    </div>

                    <div class="custom-control custom-checkbox mb-3">
                        <input type="hidden" name="alert_nttn_status" value="0">
                        <input type="checkbox" class="custom-control-input" id="alert_nttn_status" name="alert_nttn_status" value="1"
                               @checked(old('alert_nttn_status', $settings->alert_nttn_status))>
                        <label class="custom-control-label" for="alert_nttn_status">An NTTN link goes DOWN or comes back UP (ping)</label>
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

                    <label class="d-block mb-1">SFP Rx warning level</label>
                    <p class="small text-muted mb-2">Alert and turn the port yellow when Rx power drops below this. Checked by link speed first.</p>

                    <div class="row">
                        <div class="col-sm-4 form-group">
                            <label class="small mb-1" for="rx_warn_10g">10G links (and faster)</label>
                            <div class="input-group">
                                <input type="number" step="0.1" id="rx_warn_10g" name="rx_warn_10g" class="form-control @error('rx_warn_10g') is-invalid @enderror"
                                       value="{{ old('rx_warn_10g', $settings->rx_warn_10g) }}" placeholder="-15">
                                <div class="input-group-append"><span class="input-group-text">dBm</span></div>
                            </div>
                        </div>
                        <div class="col-sm-4 form-group">
                            <label class="small mb-1" for="rx_warn_1g">1G links</label>
                            <div class="input-group">
                                <input type="number" step="0.1" id="rx_warn_1g" name="rx_warn_1g" class="form-control @error('rx_warn_1g') is-invalid @enderror"
                                       value="{{ old('rx_warn_1g', $settings->rx_warn_1g) }}" placeholder="-18">
                                <div class="input-group-append"><span class="input-group-text">dBm</span></div>
                            </div>
                        </div>
                        <div class="col-sm-4 form-group">
                            <label class="small mb-1" for="rx_low_threshold">Other speeds (fallback)</label>
                            <div class="input-group">
                                <input type="number" step="0.1" id="rx_low_threshold" name="rx_low_threshold" class="form-control @error('rx_low_threshold') is-invalid @enderror"
                                       value="{{ old('rx_low_threshold', $settings->rx_low_threshold) }}" placeholder="optional">
                                <div class="input-group-append"><span class="input-group-text">dBm</span></div>
                            </div>
                        </div>
                    </div>
                    <small class="form-text text-muted mt-0">
                        Leave a speed blank to use the module's own low-warning limit (Cisco) for those ports.
                        The fallback applies only when neither a speed rule nor a module limit exists.
                    </small>

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
