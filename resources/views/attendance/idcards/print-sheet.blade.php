<!DOCTYPE html>
{{-- Office ID cards on A4 landscape: 5 a page, fronts on top, each back below, dashed cut lines. --}}
@php
    $logo = asset('images/logo.png') . '?v=' . filemtime(public_path('images/logo.png'));
    $W = 53.98; $H = 85.6; $gap = 3; $rowGap = 10;
    $x0 = (297 - (5 * $W + 4 * $gap)) / 2;
    $y0 = (210 - (2 * $H + $rowGap)) / 2;
    $pages = $items->chunk(5);
    $fileName = 'Sunlit ID Cards ' . now()->format('Y-m-d');
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ID Cards — print</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @include('components.print.id-card-style')
    <style>
        @page { size: A4 landscape; margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { background: #e2e8f0; font-family: 'Poppins', Arial, sans-serif; }
        .bar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: .5rem; padding: .6rem; background: #0f172a; color: #e2e8f0; font-size: 13px; }
        .bar a, .bar button { padding: .45rem .9rem; border-radius: .4rem; border: 1px solid #334155; background: #1e293b; color: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .bar button.go { background: #16a34a; border-color: #16a34a; }
        .bar button.pr { background: #4f46e5; border-color: #4f46e5; }
        .opts { position: sticky; top: 48px; z-index: 4; display: flex; justify-content: center; padding: .55rem; background: #fff; border-bottom: 1px solid #cbd5e1; }
        .idp { display: flex; flex-direction: column; gap: .4rem; font-size: 12px; color: #334155; }
        .idp-row { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .6rem; }
        .idp-k { font-weight: 700; color: #475569; }
        .idp-pill { cursor: pointer; } .idp-pill input { display: none; }
        .idp-pill span { display: inline-block; padding: .25rem .65rem; border: 1px solid #cbd5e1; border-radius: 999px; font-weight: 600; }
        .idp-pill input:checked + span { border-color: #4f46e5; background: #eef2ff; color: #4338ca; }
        .idp-sel { font: inherit; padding: .2rem .4rem; border: 1px solid #cbd5e1; border-radius: .3rem; }
        .idp-check { display: inline-flex; gap: .35rem; align-items: flex-start; cursor: pointer; }
        .sheet { position: relative; width: 297mm; height: 210mm; margin: 6mm auto; overflow: hidden; background: #fff; box-shadow: 0 2px 10px rgba(0, 0, 0, .2); page-break-after: always; break-after: page; }
        .sheet:last-child { page-break-after: auto; break-after: auto; }
        .cut { position: absolute; width: {{ $W }}mm; height: {{ $H }}mm; outline: .2mm dashed #64748b; }
        .cut .idc { border-radius: 0; transform-origin: 50% 50%; }
        .foldmark { position: absolute; height: 0; border-top: .25mm dashed #0f172a; }
        .foldmark span { position: absolute; right: 100%; top: 0; margin-right: .4mm; transform: translateY(-50%); font: 700 6pt Arial, sans-serif; color: #0f172a; }
        .mark { position: absolute; width: 0; height: 2.5mm; border-left: .15mm solid #0f172a; }
        .note { position: absolute; left: 0; right: 0; bottom: 3mm; text-align: center; font: 7pt Arial, sans-serif; color: #64748b; }
        .void { position: absolute; left: 0; right: 0; top: 40%; text-align: center; font: 700 7mm Arial; color: rgba(220, 38, 38, .75); transform: rotate(-30deg); pointer-events: none; }
        @media print {
            body { background: #fff; }
            .bar, .opts { display: none; }
            .sheet { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="bar">
        <a href="{{ route('attendance.idcards.index') }}">← Back</a>
        <span>{{ $items->count() }} {{ $items->count() === 1 ? 'card' : 'cards' }} · {{ $pages->count() }} A4 {{ $pages->count() === 1 ? 'page' : 'pages' }} (landscape)</span>
        <button type="button" class="go" id="pdf-a4">⬇ Download PDF</button>
        <button type="button" class="pr" id="print">🖨 Print</button>
    </div>
    <div class="opts">@include('attendance.idcards.partials.print-options', ['copies' => false])</div>

    @foreach ($pages as $page)
        <div class="sheet">
            @foreach ($page->values() as $slot => $item)
                @php $x = $x0 + $slot * ($W + $gap); @endphp
                <div class="cut card-pair" data-side="front" style="left: {{ $x }}mm; top: {{ $y0 }}mm"></div>
                <div class="cut" data-side="back" style="left: {{ $x }}mm; top: {{ $y0 + $H + $rowGap }}mm"></div>
                <div class="mark" style="left: {{ $x }}mm; top: {{ $y0 - 4 }}mm"></div>
                <div class="mark" style="left: {{ $x + $W }}mm; top: {{ $y0 - 4 }}mm"></div>
                <div class="foldmark" style="left: {{ $x - 1.4 }}mm; width: {{ $W + 2.8 }}mm">@if ($slot === 0)<span>FOLD</span>@endif</div>
                <template class="card-src">@include('components.print.id-card', ['c' => $item['c'], 's' => $settings, 't' => $item['t'], 'logo' => $logo, 'signature' => $signature])</template>
                @unless ($item['valid'])
                    <div class="void" style="left: {{ $x }}mm; width: {{ $W }}mm; right: auto; top: {{ $y0 + 30 }}mm">NOT VALID</div>
                @endunless
            @endforeach
            <div class="note">Sunlit Network DC — office ID cards · top: front, below: back · cut along the dashed lines · print at 100% (actual size)</div>
        </div>
    @endforeach

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
            integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="{{ asset('js/idcard-sheet.js') }}?v={{ filemtime(public_path('js/idcard-sheet.js')) }}"></script>
    <script>
    (function () {
        // Put each card's front and back into its two cut boxes.
        document.querySelectorAll('template.card-src').forEach(function (tpl) {
            var frag = tpl.content.cloneNode(true), cards = frag.querySelectorAll('.idc');
            var front = tpl.previousElementSibling.previousElementSibling.previousElementSibling.previousElementSibling.previousElementSibling;
            var back = front.nextElementSibling;
            front.appendChild(cards[0]); back.appendChild(cards[1]);
            tpl.remove();
        });
        // QR codes (plain ASCII links).
        document.querySelectorAll('[data-qr]').forEach(function (box) {
            var tmp = document.createElement('div');
            new QRCode(tmp, { text: box.dataset.qr.replace(/[^\x20-\x7E]/g, ''), width: 300, height: 300, colorDark: '#0f172a', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
            var img = new Image(); img.alt = ''; img.src = tmp.querySelector('canvas').toDataURL('image/png');
            box.innerHTML = ''; box.appendChild(img);
        });
        var options = IdSheet.bindOptions(document.getElementById('idc-print-opts'));

        // Show the sheet the way it will print: fold-over (back upside down below the front) or separate rows.
        function relayout() {
            var o = options(), L = IdSheet.layout(o);
            document.querySelectorAll('.sheet').forEach(function (sheet) {
                sheet.querySelectorAll('.cut[data-side="front"]').forEach(function (f, slot) {
                    var b = f.nextElementSibling, marks = [b.nextElementSibling, b.nextElementSibling.nextElementSibling];
                    var fold = marks[1].nextElementSibling, x = L.x(slot);
                    f.style.left = b.style.left = x + 'mm';
                    f.style.top = L.yf + 'mm';
                    b.style.top = L.yb + 'mm';
                    marks[0].style.left = x + 'mm'; marks[1].style.left = (x + {{ $W }}) + 'mm';
                    marks.forEach(function (m) { m.style.top = (L.yf - L.bleed - 4) + 'mm'; });
                    fold.style.display = o.fold ? '' : 'none';
                    fold.style.left = (x - 1.4) + 'mm';
                    fold.style.top = L.foldY + 'mm';
                    f.querySelector('.idc').style.transform = o.mirror ? 'scaleX(-1)' : '';
                    b.querySelector('.idc').style.transform = (L.backDeg ? 'rotate(180deg) ' : '') + (o.mirror ? 'scaleX(-1)' : '');
                    // Bleed: shown as a soft frame (the print stretches the card's own edge colours).
                    [f, b].forEach(function (c) { c.style.boxShadow = L.bleed ? '0 0 0 ' + L.bleed + 'mm rgba(148, 163, 184, .35)' : ''; c.style.outlineStyle = L.bleed ? 'none' : 'dashed'; });
                });
            });
            document.querySelectorAll('.note').forEach(function (n) {
                n.textContent = 'Sunlit Network DC — office ID cards · ' + (o.fold ? 'fold on the dark dashed line, then cut / die-cut on the marks' : 'top: front, below: back · cut on the marks') + ' · print at 100% (actual size)' + (o.mirror ? ' · MIRRORED' : '');
            });
        }
        document.getElementById('idc-print-opts').addEventListener('change', relayout);
        relayout();
        var pairs = function () {
            return Array.prototype.map.call(document.querySelectorAll('.cut[data-side="front"]'), function (f) {
                return { front: f.querySelector('.idc'), back: f.nextElementSibling.querySelector('.idc') };
            });
        };
        document.getElementById('print').addEventListener('click', function () {
            if (!IdSheet.print(pairs(), @json($fileName), options())) alert('Allow pop-ups for this site to print.');
        });
        document.getElementById('pdf-a4').addEventListener('click', async function () {
            var btn = this, label = btn.textContent, o = options();
            btn.disabled = true;
            try {
                await IdSheet.pdf(pairs(), @json($fileName) + (o.paper === 'pvc' ? ' card' : ' A4') + (o.mirror ? ' mirror' : ''), o, function (i, n) { btn.textContent = 'Preparing ' + i + ' / ' + n + '…'; });
            } catch (e) { alert('Could not make the PDF: ' + e.message); }
            finally { btn.disabled = false; btn.textContent = label; }
        });
    })();
    </script>
</body>
</html>
