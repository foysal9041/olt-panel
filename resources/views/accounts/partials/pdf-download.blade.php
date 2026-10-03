{{--
    "Download PDF" for pages of <x-print.pad> A4 sheets: each .pad is drawn to a
    canvas (html2canvas) and placed full-page in a PDF (jsPDF), so the file
    looks exactly like the page. Needs a #pdf-btn button and $pdfName;
    ?download=1 starts it on load.
--}}
<script>
/*
 * Download PDF: each A4 pad page is drawn to a canvas (html2canvas) and
 * placed full-page in a PDF (jsPDF), so the file looks exactly like the
 * page on screen. ?download=1 starts it on load (download links elsewhere).
 */
(function () {
    var btn = document.getElementById('pdf-btn');
    var fileName = @json($pdfName);
    if (!btn) return;
    var libs = null;

    function load(src, integrity) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.integrity = integrity;
            s.crossOrigin = 'anonymous';
            s.referrerPolicy = 'no-referrer';
            s.onload = resolve;
            s.onerror = function () { reject(new Error('could not load ' + src.split('/').pop())); };
            document.head.appendChild(s);
        });
    }

    function ready() {
        libs = libs || Promise.all([
            load('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
                'sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA=='),
            load('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
                'sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA=='),
        ]);
        return libs;
    }

    async function download() {
        var label = btn.textContent;
        var scroll = window.scrollY;
        btn.disabled = true;
        btn.textContent = 'Preparing…';

        try {
            await ready();
            if (document.fonts) await document.fonts.ready;
            window.scrollTo(0, 0);

            var pads = document.querySelectorAll('.pad');
            var pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait', compress: true });
            var scale = pads.length > 5 ? 1.6 : 2.2;

            for (var i = 0; i < pads.length; i++) {
                btn.textContent = pads.length > 1 ? 'Preparing ' + (i + 1) + ' / ' + pads.length + '…' : 'Preparing…';
                var canvas = await html2canvas(pads[i], { scale: scale, backgroundColor: '#ffffff', logging: false });
                if (i) pdf.addPage();
                pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, 210, 210 * canvas.height / canvas.width, undefined, 'FAST');
            }

            pdf.save(fileName);
        } catch (e) {
            alert('Could not make the PDF: ' + (e && e.message ? e.message : e) + '\nYou can still use Print → Save as PDF.');
        } finally {
            window.scrollTo(0, scroll);
            btn.disabled = false;
            btn.textContent = label;
        }
    }

    btn.addEventListener('click', download);

    if (new URLSearchParams(location.search).get('download') === '1') {
        window.addEventListener('load', download);
    }
})();
</script>
