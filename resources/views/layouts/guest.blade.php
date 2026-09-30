<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#071a33">

    <title>Sign in · Sunlit Network</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-icon.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
</head>

<body class="auth-body">

@php
    $logo = asset('images/logo.png') . '?v=' . filemtime(public_path('images/logo.png'));
    $mark = asset('images/logo-icon.png') . '?v=' . filemtime(public_path('images/logo-icon.png'));
@endphp

<div class="auth-shell">

    {{-- ================= Brand panel (desktop) ================= --}}
    <aside class="auth-brand" aria-label="Sunlit Network">

        {{-- Orbit rings (echo the logo) and a live-looking network map --}}
        <svg class="auth-art" viewBox="0 0 800 900" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <defs>
                <radialGradient id="auth-halo" cx="72%" cy="30%" r="60%">
                    <stop offset="0" stop-color="#38bdf8" stop-opacity=".22"/>
                    <stop offset="1" stop-color="#38bdf8" stop-opacity="0"/>
                </radialGradient>
            </defs>
            <rect width="800" height="900" fill="url(#auth-halo)"/>
            <g class="auth-orbits" fill="none" stroke="#7dd3fc">
                <ellipse cx="610" cy="250" rx="300" ry="120" transform="rotate(-28 610 250)"/>
                <ellipse cx="610" cy="250" rx="230" ry="230"/>
                <ellipse cx="610" cy="250" rx="360" ry="150" transform="rotate(-28 610 250)" stroke-dasharray="2 10"/>
            </g>
            <g class="auth-links" stroke="#38bdf8" fill="none">
                <path d="M90 620 L230 540 L380 600 L520 500 L690 560"/>
                <path d="M230 540 L260 420 L420 380 L520 500"/>
                <path d="M380 600 L360 740 L560 780 L690 560"/>
                <path d="M420 380 L600 330 L690 560"/>
                <path d="M90 620 L140 780 L360 740"/>
            </g>
            <g class="auth-nodes" fill="#7dd3fc">
                <circle cx="90" cy="620" r="4"/><circle cx="230" cy="540" r="5"/><circle cx="380" cy="600" r="4"/>
                <circle cx="520" cy="500" r="6"/><circle cx="690" cy="560" r="4"/><circle cx="260" cy="420" r="4"/>
                <circle cx="420" cy="380" r="5"/><circle cx="600" cy="330" r="4"/><circle cx="360" cy="740" r="5"/>
                <circle cx="560" cy="780" r="4"/><circle cx="140" cy="780" r="4"/>
            </g>
            <g class="auth-pulses" fill="none" stroke="#38bdf8">
                <circle cx="520" cy="500" r="6"/><circle cx="230" cy="540" r="5"/><circle cx="360" cy="740" r="5"/>
            </g>
        </svg>

        <div class="auth-brand-top">
            <img src="{{ $logo }}" alt="Sunlit Network DC" class="auth-brand-logo">
        </div>

        <div class="auth-brand-body">
            <span class="auth-eyebrow"><span class="auth-eyebrow-dot"></span> Sunlit Network DC</span>

            <h1 class="auth-brand-title">Network Operations<br>&amp; Business Panel</h1>

            <p class="auth-brand-text">
                Monitor OLTs, switches, links and latency, keep every IP and VLAN in one register,
                and run accounts and attendance — all in one secure place.
            </p>

            <ul class="auth-points">
                <li>
                    <span class="auth-point-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><path d="M6 6.5h.01M6 17.5h.01"/></svg></span>
                    <span><strong>NOC</strong><small>OLTs · switches · NTTN · latency</small></span>
                </li>
                <li>
                    <span class="auth-point-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg></span>
                    <span><strong>IP &amp; VLAN</strong><small>One register, no duplicates</small></span>
                </li>
                <li>
                    <span class="auth-point-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></span>
                    <span><strong>Accounts</strong><small>Cash book · billing · salary</small></span>
                </li>
                <li>
                    <span class="auth-point-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 11c0 3-1 6-3 8M8.5 7.5A5 5 0 0 1 17 11c0 2-.3 4-1 6M12 3a8 8 0 0 1 8 8c0 1-.1 2.2-.3 3.3M4 17c.7-1.8 1-3.8 1-6a7 7 0 0 1 2-5"/></svg></span>
                    <span><strong>Attendance</strong><small>Devices · shifts · leave</small></span>
                </li>
            </ul>
        </div>

        <div class="auth-brand-foot">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            Encrypted session · Authorized personnel only
        </div>
    </aside>

    {{-- ================= Form ================= --}}
    <main class="auth-main">
        <div class="auth-panel">

            <div class="auth-mobile-brand">
                <img src="{{ $logo }}" alt="Sunlit Network DC">
            </div>

            <div class="auth-card">
                <img src="{{ $mark }}" alt="" class="auth-card-mark" aria-hidden="true">
                {{ $slot }}
            </div>

            <p class="auth-footer">
                &copy; {{ now()->year }} Sunlit Network DC. All rights reserved.<br>
                Developed by <strong>Foysal</strong>, CTO, Sunlit Network
            </p>

        </div>
    </main>

</div>

</body>
</html>
