@use('App\Support\Branding')
<footer class="main-footer">
    <strong>Copyright &copy; {{ now()->year }} {{ Branding::copyrightHolder() }}.</strong>
    {{ \App\Support\Ui::t('All rights reserved.') }}
    @foreach (Branding::legalLinks() as $label => $url)
        · <a href="{{ $url }}" target="_blank" rel="noopener">{{ \App\Support\Ui::t($label) }}</a>
    @endforeach
    @if ($credit = Branding::footerCredit())
        <div class="float-right d-none d-sm-inline-block">
            {{ $credit }}
        </div>
    @endif
</footer>
