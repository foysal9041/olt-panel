@extends('adminlte::page')

@section('title', 'Settings')

@section('content_header')
<h1>System Settings</h1>
@stop

@section('content')

<div class="row">

    <div class="col-md-6">

        <div class="card card-success">

            <div class="card-header">
                <h3 class="card-title">
                    System Information
                </h3>
            </div>

            <div class="card-body">

                <table class="table table-bordered">

                    <tr>
                        <th>Application</th>
                        <td>NOC Monitoring</td>
                    </tr>

                    <tr>
                        <th>Version</th>
                        <td>1.0</td>
                    </tr>

                    <tr>
                        <th>Auto Refresh</th>
                        <td>30 Seconds</td>
                    </tr>

                </table>

            </div>

        </div>

    </div>

    @can('access-settings-telegram')
        @php($telegram = \App\Models\NocAlertSetting::current())
        <div class="col-md-6">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title"><i class="fab fa-telegram-plane"></i> Telegram Alerts</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered mb-3">
                        <tr>
                            <th>Status</th>
                            <td>
                                @if ($telegram->telegram_enabled && $telegram->telegram_bot_token && $telegram->chatIds())
                                    <span class="badge badge-success">ON</span>
                                @elseif ($telegram->telegram_enabled)
                                    <span class="badge badge-warning">ON — setup incomplete</span>
                                @else
                                    <span class="badge badge-secondary">OFF</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Bot token</th>
                            <td>{{ $telegram->telegram_bot_token ? 'Saved' : 'Not set' }}</td>
                        </tr>
                        <tr>
                            <th>Chats</th>
                            <td>{{ count($telegram->chatIds()) ?: 'None' }}</td>
                        </tr>
                    </table>
                    <a href="{{ route('settings.telegram') }}" class="btn btn-info btn-sm">
                        <i class="fas fa-cog"></i> Configure Telegram
                    </a>
                </div>
            </div>
        </div>
    @endcan

</div>

@stop
