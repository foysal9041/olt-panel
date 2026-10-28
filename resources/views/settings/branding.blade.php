@extends('adminlte::page')

@section('title', 'Company Branding')

@use('App\Support\Branding')
@php
    $err = fn ($f) => $errors->has($f) ? ' is-invalid' : '';
    $val = fn ($f) => old($f, $b[$f] ?? '');
    $colors = [
        'primary' => ['Primary', 'Buttons, links and highlights'],
        'secondary' => ['Secondary', 'Sidebar and the sign-in panel'],
        'accent' => ['Accent', 'Current menu item and sign-in accents'],
        'background' => ['Background', 'Page background'],
    ];
    $files = [
        'logo' => ['Logo', 'Wide logo for documents, e-mails and the sign-in page. PNG with a transparent background works best.', 'png,jpg,webp'],
        'icon' => ['Icon', 'Square mark for the sidebar, ID cards and letterheads.', 'png,jpg,webp'],
        'favicon' => ['Favicon', 'Browser tab icon (PNG or ICO). Without one, the icon is used.', 'png,ico'],
    ];
@endphp

@section('content_header')
<x-settings.header title="Company Branding" icon="fas fa-palette"
    subtitle="This installation's company: its name, contact details, logos and colours on every page, document and e-mail" />
@stop

@section('content')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $e)
            <div>{{ $e }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('settings.branding.update') }}" enctype="multipart/form-data" autocomplete="off">
    @csrf
    @method('PUT')

<div class="row">
    <div class="col-lg-7">

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-building mr-1 text-primary"></i> Company</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="col-md-6 form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" maxlength="100" required class="form-control{{ $err('name') }}" value="{{ $val('name') }}">
                        <small class="text-muted">Short name: sidebar, browser title, alt text.</small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Legal name</label>
                        <input type="text" name="legal_name" maxlength="150" class="form-control{{ $err('legal_name') }}" value="{{ $val('legal_name') }}" placeholder="{{ Branding::name() }}">
                        <small class="text-muted">On invoices, receipts, letters, ID cards and the copyright line.</small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Application title</label>
                        <input type="text" name="app_title" maxlength="100" class="form-control{{ $err('app_title') }}" value="{{ $val('app_title') }}" placeholder="{{ Branding::name() }} ERP">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Sign-in headline</label>
                        <input type="text" name="tagline" maxlength="150" class="form-control{{ $err('tagline') }}" value="{{ $val('tagline') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-address-card mr-1 text-primary"></i> Contact details</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="col-md-6 form-group">
                        <label>Email</label>
                        <input type="email" name="email" maxlength="150" class="form-control{{ $err('email') }}" value="{{ $val('email') }}" placeholder="info@example.com">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Accounts email</label>
                        <input type="email" name="accounts_email" maxlength="150" class="form-control{{ $err('accounts_email') }}" value="{{ $val('accounts_email') }}" placeholder="Same as email">
                        <small class="text-muted">On money receipts and account prints.</small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" maxlength="60" class="form-control{{ $err('phone') }}" value="{{ $val('phone') }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Website</label>
                        <input type="text" name="website" maxlength="150" class="form-control{{ $err('website') }}" value="{{ $val('website') }}" placeholder="www.example.com">
                    </div>
                    <div class="col-md-8 form-group">
                        <label>Address</label>
                        <textarea name="address" rows="2" maxlength="500" class="form-control{{ $err('address') }}" placeholder="One printed line per line">{{ $val('address') }}</textarea>
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Short address</label>
                        <input type="text" name="address_short" maxlength="150" class="form-control{{ $err('address_short') }}" value="{{ $val('address_short') }}" placeholder="The address's last line">
                        <small class="text-muted">Small letterheads.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-file-invoice mr-1 text-primary"></i> Documents and footer</h3></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="col-md-4 form-group">
                        <label>Invoice number prefix <span class="text-danger">*</span></label>
                        <input type="text" name="invoice_prefix" maxlength="20" required class="form-control mono{{ $err('invoice_prefix') }}" value="{{ $val('invoice_prefix') }}">
                        <small class="text-muted">New invoices: {{ Branding::invoicePrefix() }}{{ strtoupper(now()->format('My')) }}/01</small>
                    </div>
                    <div class="col-md-8 form-group">
                        <label>Copyright holder</label>
                        <input type="text" name="copyright_holder" maxlength="150" class="form-control{{ $err('copyright_holder') }}" value="{{ $val('copyright_holder') }}" placeholder="{{ Branding::legalName() }}">
                    </div>
                    <div class="col-12 form-group">
                        <label>Footer credit line</label>
                        <input type="text" name="footer_credit" maxlength="200" class="form-control{{ $err('footer_credit') }}" value="{{ $val('footer_credit') }}" placeholder="e.g. Managed by *IT Department*">
                        <small class="text-muted">Right side of the footer and under the sign-in form. Put a word between *stars* to make it bold. Leave empty for none.</small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Privacy policy link</label>
                        <input type="url" name="privacy_url" maxlength="255" class="form-control{{ $err('privacy_url') }}" value="{{ $val('privacy_url') }}" placeholder="https://…">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Terms of service link</label>
                        <input type="url" name="terms_url" maxlength="255" class="form-control{{ $err('terms_url') }}" value="{{ $val('terms_url') }}" placeholder="https://…">
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="col-lg-5">

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-image mr-1 text-primary"></i> Logos</h3></div>
            <div class="card-body">
                @foreach ($files as $which => [$label, $help, $types])
                    <div class="brand-file">
                        <div class="brand-file-preview {{ $which === 'logo' ? 'is-wide' : '' }}">
                            <img src="{{ Branding::url($which) }}" alt="{{ $label }}">
                        </div>
                        <div class="flex-grow-1" style="min-width:0">
                            <label class="mb-1">{{ $label }}</label>
                            @if (Branding::uploaded($which))
                                <span class="badge badge-success">Uploaded</span>
                            @else
                                <span class="badge badge-light border">{{ $which === 'favicon' ? 'Uses the icon' : 'Made from the name' }}</span>
                            @endif
                            <input type="file" name="{{ $which }}" accept=".{{ str_replace(',', ',.', $types) }}" class="form-control-file small{{ $err($which) }}">
                            <small class="text-muted d-block">{{ $help }}</small>
                            @if (Branding::uploaded($which))
                                <div class="custom-control custom-checkbox mt-1">
                                    <input type="checkbox" class="custom-control-input" id="remove-{{ $which }}" name="remove_{{ $which }}" value="1">
                                    <label class="custom-control-label small" for="remove-{{ $which }}">Remove</label>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
                <small class="text-muted">PNG, JPG or WebP up to {{ config('branding.upload_max_kb') / 1024 }} MB.</small>
            </div>
        </div>

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-fill-drip mr-1 text-primary"></i> Colours</h3></div>
            <div class="card-body">
                @foreach ($colors as $c => [$label, $help])
                    @php $own = Branding::color($c); @endphp
                    <div class="brand-color">
                        <input type="color" name="{{ $c }}_color" value="{{ old($c . '_color', $own ?? Branding::DESIGN[$c]) }}" class="brand-swatch" aria-label="{{ $label }}">
                        <div class="flex-grow-1">
                            <div class="font-weight-bold">{{ $label }}</div>
                            <small class="text-muted">{{ $help }}</small>
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="custom-{{ $c }}" name="custom_{{ $c }}" value="1" @checked(old('custom_' . $c, $own !== null))>
                            <label class="custom-control-label small" for="custom-{{ $c }}">Use mine</label>
                        </div>
                    </div>
                @endforeach
                <small class="text-muted">Switched off, a colour stays as the design's own. The layout never changes, only these colours.</small>
            </div>
        </div>

        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-globe mr-1 text-primary"></i> Domain</h3></div>
            <div class="card-body">
                <div class="mono">{{ $appUrl }}</div>
                <small class="text-muted">The panel's address is part of this server's setup (APP_URL in .env, with nginx and the HTTPS certificate). Each company has its own installation, domain and database.</small>
            </div>
        </div>

    </div>
</div>

<div class="mb-4">
    <button class="btn btn-primary"><i class="fas fa-save"></i> Save branding</button>
</div>

</form>

@stop

@section('css')
<style>
    .brand-file { display: flex; gap: .9rem; align-items: flex-start; padding-bottom: .9rem; margin-bottom: .9rem; border-bottom: 1px solid #eef2f7; }
    .brand-file-preview { flex: none; display: grid; place-items: center; width: 4.5rem; height: 4.5rem; padding: .35rem; border: 1px solid #e2e8f0; border-radius: .6rem;
        background: repeating-conic-gradient(#f1f5f9 0% 25%, #fff 0% 50%) 50% / 12px 12px; }
    .brand-file-preview.is-wide { width: 9rem; }
    .brand-file-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .brand-color { display: flex; gap: .8rem; align-items: center; padding: .55rem 0; border-bottom: 1px solid #f1f5f9; }
    .brand-color:last-of-type { border-bottom: 0; }
    .brand-swatch { width: 2.6rem; height: 2.2rem; padding: 0; border: 1px solid #e2e8f0; border-radius: .45rem; background: none; cursor: pointer; }
</style>
@stop
