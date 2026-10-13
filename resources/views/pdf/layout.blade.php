{{--
    PDF pages made on the server (App\Services\PdfMaker, dompdf) for emailing.
    Each .pad is one A4 page of the company pad, drawn the way
    components/print/pad.blade.php draws it in the browser, but only with
    what dompdf understands: absolute boxes, tables and images — no flexbox,
    gradients or web fonts. Keep the two in step when the pad changes.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @font-face { font-family: 'Carlito'; font-weight: normal; src: url('{{ resource_path('fonts/Carlito-Regular.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Carlito'; font-weight: bold; src: url('{{ resource_path('fonts/Carlito-Bold.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Hind Siliguri'; font-weight: normal; src: url('{{ resource_path('fonts/HindSiliguri-Regular.ttf') }}') format('truetype'); }
    @font-face { font-family: 'Hind Siliguri'; font-weight: bold; src: url('{{ resource_path('fonts/HindSiliguri-Bold.ttf') }}') format('truetype'); }

    @page { size: A4 portrait; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; }
    body { font-family: 'Carlito', sans-serif; color: #000; font-size: 12pt; }
    .tk { font-family: 'Hind Siliguri', sans-serif; }

    .pad { position: relative; width: 210mm; height: 296.9mm; overflow: hidden; page-break-after: always; background: #f5f5f5; }
    .pad.last { page-break-after: auto; }
    .pad-bg { position: absolute; left: 0; top: 0; width: 210mm; height: 296.9mm; }
    .pad-mark { position: absolute; left: 37mm; top: 76mm; width: 136mm; height: 136mm; opacity: .2; }
    .pad-logo { position: absolute; left: 18mm; top: 9mm; width: 54mm; height: 21mm; }
    .pad-contact { position: absolute; left: 110mm; width: 82.5mm; top: 12.5mm; text-align: right; font-size: 8.5pt; line-height: 1.1; color: #333; }
    .pad-contact div { height: 5.6mm; }
    .pad-contact img { width: 3.2mm; height: 3.2mm; margin-left: 2.6mm; vertical-align: middle; }
    .pad-rule { position: absolute; left: 19.5mm; width: 173mm; top: 35mm; height: .3mm; background: #8f8f8f; }
    .pad-rule-bar { position: absolute; left: 19.5mm; width: 56.7mm; top: 34mm; height: 1.3mm; background: #0d6fd8; }
    .pad-foot { position: absolute; left: 0; width: 210mm; bottom: 0; height: 17mm; background: #0d6fd8; border-top: .6mm solid #3a9cf2; color: #fff; }
    .pad-web { position: absolute; left: 9.5mm; bottom: 5mm; font-size: 8.5pt; }
    .pad-web img { width: 4.2mm; height: 4.2mm; margin-right: 2.2mm; vertical-align: -1.2mm; }
    .pad-addr { position: absolute; left: 100mm; width: 102mm; bottom: 3.8mm; font-size: 8.3pt; line-height: 1.15; text-align: right; }

    /*
     * The writing area between the rule and the footer: the page's text, then
     * the signatures. The text cell's height is a minimum, so the signatures
     * sit near the bottom of the area — and move down when the text is long
     * instead of running into it.
     */
    .sheet { position: absolute; left: 19.5mm; top: 42mm; width: 173mm; border-collapse: collapse; }
    .sheet-body { height: 178mm; padding: 0; vertical-align: top; }
    .sheet-signs { padding: 6mm 0 0; vertical-align: bottom; }
    table.signs { width: 100%; border-collapse: collapse; }
    table.signs td { width: 64mm; text-align: center; vertical-align: top; line-height: 1.2; padding: 0; }
    table.signs td.gap { width: auto; }
    .signs .role { font-weight: bold; text-decoration: underline; margin: 0 0 14mm; }
    .signs .line { border-top: 1.5px dotted #000; margin-bottom: 1.8mm; height: 0; }

    .top { width: 100%; border-collapse: collapse; margin-bottom: 9mm; }
    .top td { padding: 0; vertical-align: top; }
    .meta { line-height: 1.3; font-weight: bold; }
    .tag { text-align: right; font-size: 20pt; font-weight: bold; letter-spacing: 2pt; color: #0d5fc0; line-height: 1; }
    .tag small { display: block; margin-top: 1.5mm; font-size: 9pt; letter-spacing: 0; color: #475569; }
    .bill-for { margin: 0 0 3mm; text-align: center; font-size: 14pt; font-weight: bold; text-decoration: underline; }
    .words { margin: 6mm 0 0; font-weight: bold; }
    @yield('styles')
</style>
</head>
<body>
@yield('content')
</body>
</html>
