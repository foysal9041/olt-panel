{{-- The pad artwork of one PDF page (see pdf/layout); the page's own text goes beside it in the .pad box. --}}
@php
    $svg = fn (string $body, string $box) => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $box . '" preserveAspectRatio="none">' . $body . '</svg>');
    $bg = $svg('<rect width="613" height="863" fill="#f5f5f5"/>'
        . '<polygon points="0,0 330,0 230,110 0,120" fill="#fbfbfb"/>'
        . '<polygon points="330,0 613,0 613,130 410,215 230,110" fill="#f0f0f0"/>'
        . '<polygon points="230,110 410,215 120,262" fill="#f7f7f7"/>'
        . '<polygon points="410,215 613,130 613,275" fill="#e8e8e8"/>'
        . '<polygon points="0,120 230,110 120,262 0,250" fill="#f9f9f9"/>'
        . '<polygon points="0,250 120,262 60,560 0,545" fill="#e5e5e5"/>'
        . '<polygon points="120,262 410,215 300,420" fill="#f3f3f3"/>'
        . '<polygon points="410,215 613,275 613,470 300,420" fill="#ebebeb"/>'
        . '<polygon points="60,560 120,262 300,420 165,690" fill="#eeeeee"/>'
        . '<polygon points="300,420 613,470 613,640 425,560" fill="#f6f6f6"/>'
        . '<polygon points="165,690 300,420 425,560" fill="#e9e9e9"/>'
        . '<polygon points="0,545 60,560 165,690 0,800" fill="#e1e1e1"/>'
        . '<polygon points="165,690 425,560 613,640 613,800 280,800" fill="#efefef"/>'
        . '<polygon points="425,560 613,640 613,560" fill="#e6e6e6"/>'
        . '<polygon points="0,800 165,690 280,800" fill="#f4f4f4"/>'
        . '<polygon points="280,800 613,800 613,720" fill="#f8f8f8"/>', '0 0 613 863');
    $phone = $svg('<path fill="#1565c0" d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/>', '0 0 24 24');
    $mail = $svg('<path fill="#1565c0" d="M2 5h20v14H2V5zm2 2v.5l8 5 8-5V7H4zm16 2.8-8 5-8-5V17h16V9.8z"/>', '0 0 24 24');
    $globe = $svg('<g fill="none" stroke="#ffffff" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 3.8 5.6 3.8 9s-1.2 6.4-3.8 9c-2.6-2.6-3.8-5.6-3.8-9S9.4 5.6 12 3z"/></g>', '0 0 24 24');
@endphp
<img class="pad-bg" src="{{ $bg }}" alt="">
<img class="pad-mark" src="{{ \App\Support\Branding::path('icon-gray') }}" alt="">
<img class="pad-logo" src="{{ \App\Support\Branding::path('logo') }}" alt="{{ \App\Support\Branding::legalName() }}">
<div class="pad-contact">
    @if (\App\Support\Branding::phone())
        <div>{{ \App\Support\Branding::phone() }}<img src="{{ $phone }}" alt=""></div>
    @endif
    @if (\App\Support\Branding::email())
        <div>{{ \App\Support\Branding::email() }}<img src="{{ $mail }}" alt=""></div>
    @endif
</div>
<div class="pad-rule"></div>
<div class="pad-rule-bar"></div>
<div class="pad-foot">
    @if (\App\Support\Branding::website())
        <div class="pad-web"><img src="{{ $globe }}" alt="">{{ \App\Support\Branding::website() }}</div>
    @endif
    <div class="pad-addr">{{ \App\Support\Branding::letterheadAddress() }}</div>
</div>
