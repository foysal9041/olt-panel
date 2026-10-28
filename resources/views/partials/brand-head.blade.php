{{-- The company's favicon and (only when set) its colours — layouts include this in <head>. --}}
<link rel="icon" href="{{ \App\Support\Branding::url('favicon') }}">
<meta name="theme-color" content="{{ \App\Support\Branding::themeColor() }}">
@if ($brandCss = \App\Support\Branding::panelCss())
    <style id="brand-colors">{{ $brandCss }}</style>
@endif
