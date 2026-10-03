{{-- A tiny picture of a card template's front: $l layout, $d / $m / $a colours. --}}
<svg viewBox="0 0 540 856" aria-hidden="true">
    <rect width="540" height="856" fill="#ffffff"/>
    @if ($l === 'angle')
        <polygon points="0,250 540,168 540,330 0,412" fill="{{ $d }}"/>
        <polygon points="0,412 540,330 540,350 0,432" fill="{{ $a }}"/>
        <polygon points="390,0 540,0 540,120" fill="{{ $a }}"/>
        <polygon points="0,812 540,768 540,856 0,856" fill="{{ $d }}"/>
        <rect x="150" y="190" width="240" height="240" rx="30" fill="#e2e8f0" stroke="{{ $a }}" stroke-width="14"/>
        <rect x="150" y="490" width="240" height="30" rx="15" fill="{{ $d }}"/>
        <rect x="70" y="580" width="400" height="16" rx="8" fill="#cbd5e1"/><rect x="70" y="625" width="360" height="16" rx="8" fill="#cbd5e1"/><rect x="70" y="670" width="380" height="16" rx="8" fill="#cbd5e1"/>
    @elseif ($l === 'classic')
        <rect width="540" height="300" fill="{{ $d }}"/>
        <rect y="300" width="540" height="14" fill="{{ $a }}"/>
        <rect x="150" y="60" width="240" height="40" rx="20" fill="#ffffff" opacity=".9"/>
        <circle cx="270" cy="300" r="122" fill="#e2e8f0" stroke="#ffffff" stroke-width="16"/>
        <rect x="150" y="470" width="240" height="30" rx="15" fill="{{ $d }}"/>
        <rect x="70" y="560" width="400" height="16" rx="8" fill="#cbd5e1"/><rect x="70" y="605" width="360" height="16" rx="8" fill="#cbd5e1"/><rect x="70" y="650" width="380" height="16" rx="8" fill="#cbd5e1"/>
        <rect y="796" width="540" height="60" fill="{{ $d }}"/>
    @else
        <path d="M0 312 C 130 238 300 300 540 196 L540 856 L0 856 Z" fill="{{ $d }}"/>
        <path d="M0 286 C 130 212 300 272 540 170 L540 200 C 300 304 130 244 0 318 Z" fill="{{ $a }}"/>
        <rect x="150" y="60" width="240" height="60" rx="30" fill="{{ $a }}" opacity=".35"/>
        <circle cx="270" cy="350" r="125" fill="#e2e8f0" stroke="#ffffff" stroke-width="16"/>
        <rect x="150" y="520" width="240" height="30" rx="15" fill="#ffffff"/>
        <rect x="70" y="600" width="400" height="16" rx="8" fill="#ffffff" opacity=".45"/><rect x="70" y="645" width="360" height="16" rx="8" fill="#ffffff" opacity=".45"/><rect x="70" y="690" width="380" height="16" rx="8" fill="#ffffff" opacity=".45"/>
        <rect y="804" width="540" height="52" fill="{{ $a }}"/>
    @endif
</svg>
