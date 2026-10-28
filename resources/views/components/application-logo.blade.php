@use('App\Support\Branding')
<div style="display:flex; align-items:center; justify-content:center; gap:20px; margin-bottom:20px;">
    <img src="{{ Branding::url('icon') }}" alt="{{ Branding::name() }}" style="width:100px; height:100px; object-fit:contain;">
    <div>
        <h2 style="margin:0; font-size:28px; font-weight:700;">{{ Branding::appTitle() }}</h2>
        <h3 style="margin:5px 0 0 0; font-size:20px; font-weight:600;">{{ Branding::legalName() }}</h3>
    </div>
</div>
