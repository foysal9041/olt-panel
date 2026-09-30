<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Sunlit Network DC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; font-family: 'Hind Siliguri', 'Noto Sans Bengali', system-ui, sans-serif; color: #0f172a; font-size: 14px; }
        .sheet { max-width: 900px; margin: 1.5rem auto; padding: 1.5rem 1.75rem; background: #fff; box-shadow: 0 2px 10px rgba(15, 23, 42, .1); }
        .sheet-head { margin-bottom: 1rem; }
        .brand { display: flex; flex-direction: column; align-items: center; text-align: center; padding-bottom: .7rem; border-bottom: 2px solid #1e3a8a; }
        .brand-title { display: flex; align-items: center; justify-content: center; gap: .6rem; margin-bottom: .2rem; }
        .brand-title img { height: 46px; width: auto; }
        .brand-title h1 { margin: 0; font-size: 1.6rem; font-weight: 700; color: #1e3a8a; letter-spacing: .01em; }
        .brand p { margin: 0; font-size: .82rem; line-height: 1.4; color: #475569; }
        .sheet-head h2 { margin: .7rem 0 0; text-align: center; font-size: 1.1rem; font-weight: 600; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #94a3b8; padding: .3rem .5rem; vertical-align: middle; text-align: center; }
        th { background: #1e3a8a; color: #fff; font-weight: 600; }
        td.num, th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        td.c, th.c { text-align: center; }
        tr.total td { background: #1e3a8a; color: #fff; font-weight: 700; }
        tr.sub td { background: #e2e8f0; font-weight: 700; }
        .muted { color: #64748b; }
        .neg { color: #b91c1c; }
        .actions { position: fixed; top: 1rem; right: 1rem; display: flex; gap: .5rem; }
        .actions button, .actions a { padding: .5rem .9rem; border-radius: .4rem; border: 1px solid #1e3a8a; background: #1e3a8a; color: #fff; font: inherit; cursor: pointer; text-decoration: none; }
        .actions a { background: #fff; color: #1e3a8a; }
        .sign { display: flex; justify-content: space-between; margin-top: 3rem; }
        .sign div { width: 30%; text-align: center; border-top: 1px solid #0f172a; padding-top: .3rem; font-size: .85rem; }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; max-width: none; padding: 0; }
            .actions { display: none; }
            @page { size: A4; margin: 12mm; }
        }
        @yield('styles')
    </style>
</head>
<body>
    <div class="actions">
        <a href="@yield('back')">← Back</a>
        @yield('actions')
        <button onclick="window.print()">Print</button>
    </div>
    <div class="sheet">
        {{-- Pages that print several documents (e.g. all invoices) put the letterhead on each one themselves. --}}
        @unless (View::hasSection('bare'))
            <div class="sheet-head">
                @include('layouts.partials.print-letterhead')
                <h2>@yield('heading')</h2>
            </div>
        @endunless
        @yield('content')
    </div>
</body>
</html>
