@php($telegramSettings = \App\Models\NocAlertSetting::current())
@if (! $telegramSettings->telegram_enabled)
    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap">
        <span><i class="fab fa-telegram-plane"></i> <strong>Telegram alerts are OFF.</strong> Thresholds are checked, but no messages are sent.</span>
        @can('access-settings-telegram')
            <a href="{{ route('settings.telegram') }}" class="btn btn-sm btn-dark mt-1 mt-md-0">Turn on in Settings → Telegram</a>
        @endcan
    </div>
@endif
