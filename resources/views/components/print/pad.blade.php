@props(['plain' => false])

{{--
    One A4 page of the Sunlit Network DC letterhead pad: low-poly grey
    background, globe watermark, logo + phone/email header with a blue/grey
    rule, and the blue address footer. The slot is laid out in the writing area
    between the rule and the footer.
    plain = true keeps the same geometry but hides the artwork, for printing
    on the office's pre-printed pad paper.
--}}

@php
    $img = fn ($file) => asset('images/' . $file) . '?v=' . filemtime(public_path('images/' . $file));
@endphp

@once
<style>
    .pad {
        position: relative; width: 210mm; height: 296.5mm; margin: 0 auto 1.5rem; overflow: hidden;
        background: #f6f6f6; color: #000; box-shadow: 0 2px 12px rgba(15, 23, 42, .15);
        page-break-after: always; break-after: page;
        -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
    .pad:last-child { page-break-after: auto; break-after: auto; }
    .pad-bg { position: absolute; inset: 0; width: 100%; height: 100%; }
    .pad-mark { position: absolute; left: 50%; top: 144mm; width: 136mm; transform: translate(-50%, -50%); opacity: .2; }

    .pad-logo { position: absolute; left: 18mm; top: 9mm; height: 21mm; width: auto; }
    .pad-contact { position: absolute; right: 17.5mm; top: 12.5mm; font-size: 8.5pt; line-height: 5.6mm; color: #333; text-align: right; }
    .pad-contact div { display: flex; align-items: center; justify-content: flex-end; gap: 3.2mm; }
    .pad-contact svg { width: 3.2mm; height: 3.2mm; fill: #1565c0; }
    .pad-rule { position: absolute; left: 19.5mm; right: 17.5mm; top: 35mm; height: .3mm; background: #8f8f8f; }
    .pad-rule::before { content: ''; position: absolute; left: 0; bottom: 0; width: 32.8%; height: 1.3mm; background: linear-gradient(90deg, #0b5cbf, #1a7fe0); }

    .pad-foot { position: absolute; left: 0; right: 0; bottom: 0; height: 17mm; color: #fff; background: linear-gradient(90deg, #0a5fc4 0%, #0d6fd8 45%, #1c8fec 100%); border-top: .6mm solid #3a9cf2; }
    .pad-foot svg.shapes { position: absolute; inset: 0; width: 100%; height: 100%; }
    .pad-web { position: absolute; left: 9.5mm; bottom: 5mm; display: flex; align-items: center; gap: 2.4mm; font-size: 8.5pt; }
    .pad-web svg { width: 4.2mm; height: 4.2mm; }
    .pad-addr { position: absolute; right: 8mm; bottom: 4mm; font-size: 8.3pt; line-height: 1.4; text-align: right; }

    .pad-area { position: absolute; left: 19.5mm; right: 17.5mm; top: 42mm; bottom: 25mm; display: flex; flex-direction: column; }

    .pad.plain { background: #fff; }
    .pad.plain > :not(.pad-area) { visibility: hidden; }

    @media screen { .sheet { margin-top: 4.2rem; } }
    @media print {
        @page { size: A4; margin: 0; }
        .pad { margin: 0; box-shadow: none; }
    }
</style>
@endonce

<section @class(['pad', 'plain' => $plain])>
    <svg class="pad-bg" viewBox="0 0 613 863" preserveAspectRatio="none" aria-hidden="true">
        <rect width="613" height="863" fill="#f5f5f5"/>
        <polygon points="0,0 330,0 230,110 0,120" fill="#fbfbfb"/>
        <polygon points="330,0 613,0 613,130 410,215 230,110" fill="#f0f0f0"/>
        <polygon points="230,110 410,215 120,262" fill="#f7f7f7"/>
        <polygon points="410,215 613,130 613,275" fill="#e8e8e8"/>
        <polygon points="0,120 230,110 120,262 0,250" fill="#f9f9f9"/>
        <polygon points="0,250 120,262 60,560 0,545" fill="#e5e5e5"/>
        <polygon points="120,262 410,215 300,420" fill="#f3f3f3"/>
        <polygon points="410,215 613,275 613,470 300,420" fill="#ebebeb"/>
        <polygon points="60,560 120,262 300,420 165,690" fill="#eeeeee"/>
        <polygon points="300,420 613,470 613,640 425,560" fill="#f6f6f6"/>
        <polygon points="165,690 300,420 425,560" fill="#e9e9e9"/>
        <polygon points="0,545 60,560 165,690 0,800" fill="#e1e1e1"/>
        <polygon points="165,690 425,560 613,640 613,800 280,800" fill="#efefef"/>
        <polygon points="425,560 613,640 613,560" fill="#e6e6e6"/>
        <polygon points="0,800 165,690 280,800" fill="#f4f4f4"/>
        <polygon points="280,800 613,800 613,720" fill="#f8f8f8"/>
    </svg>
    <img class="pad-mark" src="{{ $img('logo-icon-gray.png') }}" alt="">

    <img class="pad-logo" src="{{ $img('logo.png') }}" alt="Sunlit Network DC">
    <div class="pad-contact">
        <div>09614-552233
            <svg viewBox="0 0 24 24"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg></div>
        <div>sunlitnetwork@gmail.com
            <svg viewBox="0 0 24 24"><path d="M2 5h20v14H2V5zm2 2v.5l8 5 8-5V7H4zm16 2.8-8 5-8-5V17h16V9.8z"/></svg></div>
    </div>
    <div class="pad-rule"></div>

    <div class="pad-area">{{ $slot }}</div>

    <footer class="pad-foot">
        <svg class="shapes" viewBox="0 0 613 50" preserveAspectRatio="none" aria-hidden="true">
            <polygon points="0,0 120,0 60,50 0,50" fill="rgba(0,0,0,.06)"/>
            <polygon points="150,0 290,0 250,50 110,50" fill="rgba(255,255,255,.05)"/>
            <polygon points="290,0 330,0 300,50 250,50" fill="rgba(255,255,255,.1)"/>
            <polygon points="330,0 470,0 420,50 300,50" fill="rgba(0,0,0,.04)"/>
            <polygon points="470,0 613,0 613,50 520,50" fill="rgba(255,255,255,.07)"/>
        </svg>
        <div class="pad-web">
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.6 2.6 3.8 5.6 3.8 9s-1.2 6.4-3.8 9c-2.6-2.6-3.8-5.6-3.8-9S9.4 5.6 12 3z"/></svg>
            www.sunlitnetwork.com
        </div>
        <div class="pad-addr">
            Sunlit Network DC, Roshid Super Market 2nd Floor<br>
            Navaron Bazar, Sharsha, Jashore
        </div>
    </footer>
</section>
