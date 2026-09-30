@php($alertSettings = \App\Models\NocAlertSetting::current())
@unless ($alertSettings->anyChannelEnabled())
    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap">
        <span><i class="fas fa-bell-slash"></i> <strong>Alerts are OFF</strong> (Telegram and WhatsApp). Thresholds are checked, but no messages are sent.</span>
        @can('access-settings-telegram')
            <span class="mt-1 mt-md-0">
                <a href="{{ route('settings.telegram') }}" class="btn btn-sm btn-dark"><i class="fab fa-telegram-plane"></i> Telegram</a>
                <a href="{{ route('settings.whatsapp') }}" class="btn btn-sm btn-success"><i class="fab fa-whatsapp"></i> WhatsApp</a>
            </span>
        @endcan
    </div>
@endunless
