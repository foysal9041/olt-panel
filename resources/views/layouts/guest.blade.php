<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('adminlte.title', 'OLT & Attendance Panel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ filemtime(public_path('css/auth.css')) }}">
</head>

<body class="antialiased">

@php($logo = asset('images/logo.png').'?v='.filemtime(public_path('images/logo.png')))

<div class="auth-shell">

    <div class="auth-bg" aria-hidden="true">
        <span class="auth-glow auth-glow--one"></span>
        <span class="auth-glow auth-glow--two"></span>
        <span class="auth-grid"></span>
    </div>

    <section class="auth-hero">

        <img src="{{ $logo }}" alt="Sunlit Network" class="auth-hero-logo">

        <span class="auth-eyebrow">Network Operations Center</span>

        <h1 class="auth-hero-title">
            Sunlit Network <span>ERP</span>
        </h1>

        <p class="auth-hero-text">
            One panel for network operations and workforce management.
        </p>

        <ul class="auth-features">
            <li>
                <span class="auth-feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><path d="M6 6.5h.01M6 17.5h.01"/></svg>
                </span>
                <div>
                    <strong>OLT Monitoring</strong>
                    <small>Live status, VLANs, IP pools &amp; NTTN links</small>
                </div>
            </li>
            <li>
                <span class="auth-feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
                <div>
                    <strong>Staff Attendance</strong>
                    <small>ZKTeco devices, shifts, leaves &amp; reports</small>
                </div>
            </li>
            <li>
                <span class="auth-feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                </span>
                <div>
                    <strong>Accounts &amp; Billing</strong>
                    <small>Customers, invoices, payments &amp; ledgers</small>
                </div>
            </li>
        </ul>

    </section>

    <main class="auth-main">

        <div class="auth-panel">

            <div class="auth-mobile-brand">
                <img src="{{ $logo }}" alt="Sunlit Network">
                <h1>Sunlit Network <span>ERP</span></h1>
            </div>

            <div class="auth-card">
                {{ $slot }}
            </div>

            <p class="auth-footer">
                &copy; {{ now()->year }} Sunlit Network DC. All rights reserved.
                <br>
                Developed by <strong>Foysal</strong>, CTO, Sunlit Network
            </p>

        </div>

    </main>

</div>

</body>
</html>
