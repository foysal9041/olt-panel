@use('App\Support\Branding')
<div class="brand">
    <div class="brand-title">
        <img src="{{ Branding::url('icon') }}" alt="{{ Branding::legalName() }}">
        <h1>{{ Branding::legalName() }}</h1>
    </div>
    @if (Branding::addressShort())
        <p>{{ Branding::addressShort() }}</p>
    @endif
    <p>
        @if (Branding::accountsEmail())
            Email: {{ Branding::accountsEmail() }}
        @endif
        @if (Branding::accountsEmail() && Branding::websiteShort())
            ·
        @endif
        @if (Branding::websiteShort())
            Website: {{ Branding::websiteShort() }}
        @endif
    </p>
</div>
