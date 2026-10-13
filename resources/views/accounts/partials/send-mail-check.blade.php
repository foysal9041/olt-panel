{{--
    "Email to client" tick box (name send_mail) for forms that make an invoice
    or record a payment. Needs $id and $label; $have / $of show how many of the
    customers have an email address. Off and explained while email isn't set
    up on Settings → Email.
--}}
@php $ready = $mailReady ?? \App\Models\MailSetting::current()->ready(); @endphp
<div class="custom-control custom-checkbox small">
    <input type="checkbox" class="custom-control-input" id="{{ $id }}" name="send_mail" value="1" @checked($ready && old('send_mail')) @disabled(! $ready)>
    <label class="custom-control-label" for="{{ $id }}"><i class="fas fa-envelope text-primary"></i> {{ $label }}</label>
    @if ($ready && isset($have))
        <span class="text-muted">— {{ $have }}/{{ $of }} have an email address</span>
    @endif
    @unless ($ready)
        <span class="text-muted">— email isn't set up yet</span>
        @can('access-settings-mail')
            <a href="{{ route('settings.mail') }}">Set up</a>
        @endcan
    @endunless
</div>
