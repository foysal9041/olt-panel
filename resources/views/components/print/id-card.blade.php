{{--
    One office ID card, front and back (CR80). $c: name, designation,
    department, id_label, id_no, joining_date, blood_group, phone, expiry,
    card_no, photo (data: URL), qr;
    $s: settings (back side); $t: theme [label, dark, mid, accent];
    $logo: logo URL. data-f / data-photo / data-qr are filled live by the
    generator page.
--}}
@php
    $initials = collect(preg_split('/\s+/', trim($c['name'] ?? '')))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
    $vars = "--d: {$t[1]}; --m: {$t[2]}; --a: {$t[3]};";
    $layout = $t[4] ?? 'wave';
    $icon_logo = asset('images/logo-icon.png') . '?v=' . filemtime(public_path('images/logo-icon.png'));
    $icon = [
        'phone' => '<svg viewBox="0 0 512 512"><path d="M497 361l-111-48a24 24 0 0 0-28 7l-49 60A371 371 0 0 1 132 202l60-49a24 24 0 0 0 7-28L151 14a24 24 0 0 0-27-14L21 24A24 24 0 0 0 2 47c0 256 207 463 463 463a24 24 0 0 0 24-19l24-103a24 24 0 0 0-14-27z"/></svg>',
        'office' => '<svg viewBox="0 0 448 512"><path d="M436 480h-20V24c0-13-11-24-24-24H56C43 0 32 11 32 24v456H12c-7 0-12 5-12 12v20h448v-20c0-7-5-12-12-12zM128 76c0-7 5-12 12-12h40c7 0 12 5 12 12v40c0 7-5 12-12 12h-40c-7 0-12-5-12-12V76zm0 96c0-7 5-12 12-12h40c7 0 12 5 12 12v40c0 7-5 12-12 12h-40c-7 0-12-5-12-12v-40zm52 148h-40c-7 0-12-5-12-12v-40c0-7 5-12 12-12h40c7 0 12 5 12 12v40c0 7-5 12-12 12zm76 160h-64v-84c0-7 5-12 12-12h40c7 0 12 5 12 12v84zm64-172c0 7-5 12-12 12h-40c-7 0-12-5-12-12v-40c0-7 5-12 12-12h40c7 0 12 5 12 12v40zm0-96c0 7-5 12-12 12h-40c-7 0-12-5-12-12v-40c0-7 5-12 12-12h40c7 0 12 5 12 12v40zm0-96c0 7-5 12-12 12h-40c-7 0-12-5-12-12V76c0-7 5-12 12-12h40c7 0 12 5 12 12v40z"/></svg>',
        'mail' => '<svg viewBox="0 0 512 512"><path d="M502 191c4-3 10 0 10 5v204c0 26-22 48-48 48H48c-26 0-48-22-48-48V196c0-5 6-8 10-5 22 17 52 39 154 113 21 15 57 48 92 47 36 1 72-32 92-47 102-74 132-96 154-113zM256 320c23 0 57-29 74-41 133-96 143-105 174-129 6-5 9-12 9-19v-19c0-26-22-48-48-48H48C22 64 0 86 0 112v19c0 7 3 14 9 19 31 24 41 33 174 129 17 12 51 41 73 41z"/></svg>',
        'web' => '<svg viewBox="0 0 496 512"><path d="M336.5 160C322 70.7 287.8 8 248 8s-74 62.7-88.5 152h177zM152 256c0 22.2 1.2 43.5 3.3 64h185.3c2.1-20.5 3.3-41.8 3.3-64s-1.2-43.5-3.3-64H155.3c-2.1 20.5-3.3 41.8-3.3 64zm324.7-96c-28.6-67.9-86.5-120.4-158-141.6 24.4 33.8 41.2 84.7 50 141.6h108zM177.2 18.4C105.8 39.6 47.8 92.1 19.3 160h108c8.7-56.9 25.5-107.8 49.9-141.6zM487.4 192H372.7c2.1 21 3.3 42.5 3.3 64s-1.2 43-3.3 64h114.6c5.5-20.5 8.6-41.8 8.6-64s-3.1-43.5-8.5-64zM120 256c0-21.5 1.2-43 3.3-64H8.6C3.2 212.5 0 233.8 0 256s3.2 43.5 8.6 64h114.6c-2-21-3.2-42.5-3.2-64zm39.5 96c14.5 89.3 48.7 152 88.5 152s74-62.7 88.5-152h-177zm159.3 141.6c71.4-21.2 129.4-73.7 158-141.6h-108c-8.8 56.9-25.6 107.8-50 141.6zM19.3 352c28.6 67.9 86.5 120.4 158 141.6-24.4-33.8-41.2-84.7-50-141.6h-108z"/></svg>',
        'pin' => '<svg viewBox="0 0 384 512"><path d="M172 501C27 291 0 269 0 192 0 86 86 0 192 0s192 86 192 192c0 77-27 99-172 309a24 24 0 0 1-40 0zm20-229a80 80 0 1 0 0-160 80 80 0 0 0 0 160z"/></svg>',
    ];
@endphp

{{-- ================= Front ================= --}}
<div class="idc idc-front idc-l-{{ $layout }}" data-layout="{{ $layout }}" style="{{ $vars }}">
    <svg class="idc-bg bgv bgv-wave" viewBox="0 0 540 856" preserveAspectRatio="none" aria-hidden="true">
        <rect width="540" height="856" fill="#ffffff"/>
        <path class="fd" d="M0 312 C 130 238 300 300 540 196 L540 856 L0 856 Z"/>
        <path class="fm" d="M0 345 C 150 270 330 330 540 230 L540 300 C 330 390 150 330 0 410 Z" opacity=".55"/>
        <path class="fa" d="M0 286 C 130 212 300 272 540 170 L540 200 C 300 304 130 244 0 318 Z"/>
        <path d="M0 268 C 120 205 280 252 540 152 L540 160 C 280 262 120 214 0 278 Z" fill="#8cc8ff" opacity=".7"/>
        {{-- network pattern --}}
        <g stroke="#8cc8ff" stroke-width="1.4" opacity=".22" fill="#8cc8ff">
            <path d="M380 330 L470 300 L520 380 L440 440 L380 330 L330 420 L440 440 M470 300 L540 270 M520 380 L540 470 L440 440 L470 540 L540 470" fill="none"/>
            <circle cx="380" cy="330" r="5"/><circle cx="470" cy="300" r="6"/><circle cx="520" cy="380" r="5"/><circle cx="440" cy="440" r="7"/>
            <circle cx="330" cy="420" r="4"/><circle cx="540" cy="470" r="5"/><circle cx="470" cy="540" r="5"/>
            <path d="M20 520 L90 470 L60 600 L20 520 M90 470 L150 520" fill="none"/>
            <circle cx="20" cy="520" r="4"/><circle cx="90" cy="470" r="5"/><circle cx="60" cy="600" r="4"/><circle cx="150" cy="520" r="4"/>
        </g>
        <rect class="fa" y="804" width="540" height="52"/>
        <rect y="798" width="540" height="6" fill="#8cc8ff" opacity=".75"/>
    </svg>
    <svg class="idc-bg bgv bgv-angle" viewBox="0 0 540 856" preserveAspectRatio="none" aria-hidden="true">
        <rect width="540" height="856" fill="#ffffff"/>
        <polygon class="fa" points="390,0 540,0 540,120"/>
        <polygon class="fd" points="450,0 540,0 540,66"/>
        <polygon class="fd" points="0,250 540,168 540,330 0,412"/>
        <polygon class="fa" points="0,412 540,330 540,343 0,425"/>
        <polygon class="fm" points="0,250 540,168 540,182 0,264" opacity=".7"/>
        <g stroke="#ffffff" stroke-width="1.4" opacity=".16" fill="#ffffff">
            <path d="M380 220 L470 200 L520 280 L440 300 Z M470 200 L540 190" fill="none"/>
            <circle cx="380" cy="220" r="5"/><circle cx="470" cy="200" r="6"/><circle cx="520" cy="280" r="5"/><circle cx="440" cy="300" r="6"/>
            <path d="M30 330 L100 300 L150 360" fill="none"/><circle cx="30" cy="330" r="4"/><circle cx="100" cy="300" r="5"/><circle cx="150" cy="360" r="4"/>
        </g>
        <polygon class="fa" points="0,800 540,756 540,768 0,812"/>
        <polygon class="fd" points="0,812 540,768 540,856 0,856"/>
    </svg>
    <svg class="idc-bg bgv bgv-classic" viewBox="0 0 540 856" preserveAspectRatio="none" aria-hidden="true">
        <rect width="540" height="856" fill="#ffffff"/>
        <rect class="fd" width="540" height="300"/>
        <path class="fm" d="M0 210 C 160 150 360 250 540 170 L540 300 L0 300 Z" opacity=".75"/>
        <rect class="fa" y="300" width="540" height="10"/>
        <g stroke="#ffffff" stroke-width="1.4" opacity=".14" fill="#ffffff">
            <path d="M20 40 L110 90 L60 170 M110 90 L200 60 M380 50 L470 110 L520 40 M470 110 L430 200" fill="none"/>
            <circle cx="20" cy="40" r="5"/><circle cx="110" cy="90" r="6"/><circle cx="60" cy="170" r="4"/><circle cx="200" cy="60" r="4"/>
            <circle cx="380" cy="50" r="5"/><circle cx="470" cy="110" r="6"/><circle cx="520" cy="40" r="4"/><circle cx="430" cy="200" r="5"/>
        </g>
        <rect class="fd" y="804" width="540" height="52"/>
        <rect class="fa" y="796" width="540" height="8"/>
    </svg>
    <img class="idc-logo" src="{{ $logo }}" alt="Sunlit Network DC">
    <div class="idc-brand"><img src="{{ $icon_logo }}" alt=""><div><b>SUNLIT</b><span>NETWORK DC</span></div></div>
    <div class="idc-photo">
        @if (! empty($c['photo']))
            <img src="{{ $c['photo'] }}" alt="" data-photo>
        @else
            <img src="" alt="" data-photo style="display:none">
            <div class="idc-ph" data-photo-ph>{{ $initials ?: '?' }}</div>
        @endif
    </div>
    <div class="idc-name" data-f="name">{{ $c['name'] ?? '' }}</div>
    <div class="idc-role" data-f="designation">{{ $c['designation'] ?? '' }}</div>
    <div class="idc-rule"></div>
    <div class="idc-info">
        @foreach ([['id', $c['id_label'] ?? 'Emp ID', $c['id_no'] ?? ''], ['department', 'Department', $c['department'] ?? ''], ['joining_date', 'Join Date', $c['joining_date'] ?? ''], ['blood_group', 'Blood Group', $c['blood_group'] ?? ''], ['phone', 'Phone', $c['phone'] ?? ''], ['expiry', 'Expiry Date', $c['expiry'] ?? '']] as [$key, $label, $value])
            <div @class(['idc-row', 'exp' => $key === 'expiry']) data-row="{{ $key }}" @style(['display:none' => $value === '' || $value === null])>
                <span class="k" @if ($key === 'id') data-f="id_label" @endif>{{ $label }}</span><span class="c">:</span><span class="v" data-f="{{ $key }}">{{ $value }}</span>
            </div>
        @endforeach
    </div>
    <div class="idc-web">{!! $icon['web'] !!} <span data-s="website">{{ $s['website'] }}</span></div>
</div>

{{-- ================= Back ================= --}}
<div class="idc idc-back idc-l-{{ $layout }}" data-layout="{{ $layout }}" style="{{ $vars }}">
    <svg class="idc-bg" viewBox="0 0 540 856" preserveAspectRatio="none" aria-hidden="true">
        <defs>
            <pattern id="idc-dots" width="14" height="14" patternUnits="userSpaceOnUse"><circle cx="7" cy="7" r="2.2" fill="#94a3b8"/></pattern>
        </defs>
        <rect width="540" height="856" fill="#ffffff"/>
        <path d="M40 300 C 120 250 200 290 260 270 C 340 240 420 300 500 260 L500 520 C 420 560 340 500 260 540 C 180 580 100 520 40 560 Z" fill="url(#idc-dots)" opacity=".18"/>
        <g class="bgv bgv-wave">
            <path class="fa" d="M0 705 C 150 690 330 722 540 650 L540 690 C 330 760 150 730 0 745 Z"/>
            <path class="fd" d="M0 735 C 150 722 330 752 540 685 L540 856 L0 856 Z"/>
        </g>
        <g class="bgv bgv-angle">
            <polygon class="fa" points="0,712 540,668 540,693 0,737"/>
            <polygon class="fd" points="0,737 540,693 540,856 0,856"/>
            <polygon class="fa" points="400,0 540,0 540,90"/>
        </g>
        <g class="bgv bgv-classic">
            <rect class="fa" y="694" width="540" height="10"/>
            <rect class="fd" y="704" width="540" height="152"/>
            <rect class="fd" width="540" height="12"/>
        </g>
    </svg>
    <img class="idc-logo" src="{{ $logo }}" alt="Sunlit Network DC">
    <div class="idc-line"></div>
    <div class="idc-contact">
        <div data-row="office_phone" @style(['display:none' => blank($s['office_phone'])])><i>{!! $icon['office'] !!}</i><span>Office: <span data-s="office_phone">{{ $s['office_phone'] }}</span></span></div>
        <div data-row="email" @style(['display:none' => blank($s['email'])])><i>{!! $icon['mail'] !!}</i><span data-s="email">{{ $s['email'] }}</span></div>
        <div data-row="website" @style(['display:none' => blank($s['website'])])><i>{!! $icon['web'] !!}</i><span data-s="website">{{ $s['website'] }}</span></div>
        <div data-row="address" @style(['display:none' => blank($s['address'])])><i>{!! $icon['pin'] !!}</i><span data-s="address">{{ $s['address'] }}</span></div>
    </div>
    <div class="idc-qr" data-qr="{{ $c['qr'] ?? '' }}"></div>
    <div class="idc-note">This card is the property of Sunlit Network DC. If found, please return it to the address above.</div>
    <div class="idc-sign">
        @if ($signature)<img src="{{ $signature }}" alt="" data-sign>@else<div class="gap" data-sign-gap></div>@endif
        <div class="ln"></div>
        <span class="lb">AUTHORIZED SIGNATURE</span>
    </div>
    @if (! empty($c['card_no']))<div class="idc-no">{{ $c['card_no'] }}</div>@endif
</div>
