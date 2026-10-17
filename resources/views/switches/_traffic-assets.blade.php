{{-- Styles and script for the Cacti-style traffic graphs (public/js/traffic-graph.js). --}}
<style>
    .tg-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: .25rem .75rem; margin-bottom: .35rem; }
    .tg-title { font-weight: 700; color: #0f172a; }
    .tg-sub { font-size: .78rem; color: #64748b; }
    .tg-plot { position: relative; height: 230px; }
    .tg[data-compact] .tg-plot { height: 150px; }
    .tg-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; padding: 1rem; text-align: center; font-size: .85rem; color: #94a3b8; }
    .tg-empty[hidden] { display: none; }
    .tg-key { display: inline-block; width: .7rem; height: .7rem; margin-right: .35rem; border-radius: 2px; vertical-align: -1px; }
    .tg-legend { font-size: .8rem; color: #334155; }
    .tg[data-compact] .tg-legend { display: flex; flex-wrap: wrap; gap: .15rem 1rem; margin-top: .25rem; }
    .tg-table { width: 100%; margin-top: .35rem; border-collapse: collapse; font-variant-numeric: tabular-nums; }
    .tg-table th, .tg-table td { padding: .2rem .5rem; border-bottom: 1px solid #eef2f7; white-space: nowrap; }
    .tg-table thead th { font-size: .7rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
    .tg-table td small { color: #94a3b8; }
    .tg-wrap { overflow-x: auto; }
</style>
<script src="{{ asset('js/traffic-graph.js') }}?v={{ filemtime(public_path('js/traffic-graph.js')) }}"></script>
<style>
    .tg-zoom { display: none; position: absolute; background: rgba(37, 99, 235, .15); border-left: 1px solid #2563eb; border-right: 1px solid #2563eb; pointer-events: none; }
    .tg[data-zoom] canvas { cursor: crosshair; }
</style>
