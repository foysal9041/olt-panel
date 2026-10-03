/*
 * Printing office ID cards. pairs: [{front: el, back: el}, …] (.idc
 * elements styled by #idc-css, components/print/id-card-style).
 *
 * Paper "a4": A4 landscape, 5 cards a page, fronts on top, each back below.
 *   fold      fold-over: each back turned 180° below its front, with a fold
 *             line in the middle of the space between them — folded there
 *             (and laminated), both sides read the right way up.
 *   foldGap   mm of space at the fold (room for the die cutter), default 10.
 *   bleed     mm of extra colour around each card, so a die cut that is a
 *             little off leaves no white edge; with bleed the cut guides are
 *             crop marks in the margins, without it a dashed line round each card.
 * Paper "pvc": one card per page, 85.6 × 54 mm, for PVC card printers:
 *   sides     both | front | back
 *   order     paired (F1 B1 F2 B2 — duplex printers) | grouped (all fronts,
 *             then all backs — single-sided printers, cards fed again)
 *   landscape the page is 85.6 wide × 54 high and the card turned 90°
 *   backFlip  backs turned 180° (if they come out upside down)
 * mirror (both papers): left-right reversed, for transfer / clear film.
 *
 *   IdSheet.print(pairs, title, opts)      prints (HTML, or images with bleed)
 *   IdSheet.pdf(pairs, fileName, opts, onProgress)
 *   IdSheet.png(pair, fileName, opts)
 *   IdSheet.layout(opts)                   where the A4 pieces go
 *   IdSheet.bindOptions(root)              → () => opts, from the form in root
 */
(function () {
    'use strict';

    var W = 53.98, H = 85.6;                 // CR80, portrait
    var PAGE_W = 297, PAGE_H = 210;          // A4 landscape
    var PER_PAGE = 5, ROW_GAP = 10;

    var DEFAULTS = { paper: 'a4', copies: 1, sides: 'both', order: 'paired', landscape: false, backFlip: false, mirror: false, fold: true, foldGap: 10, bleed: 1 };

    // ---- Libraries & drawing a side -------------------------------------------

    var libs = null;
    function load(src, integrity) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src; s.integrity = integrity; s.crossOrigin = 'anonymous'; s.referrerPolicy = 'no-referrer';
            s.onload = resolve;
            s.onerror = function () { reject(new Error('could not load ' + src.split('/').pop())); };
            document.head.appendChild(s);
        });
    }
    function ready() {
        libs = libs || Promise.all([
            window.html2canvas ? null : load('https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js', 'sha512-BNaRQnYJYiPSqHHDb58B0yaPfCu+Wgds8Gp/gU33kqBtgNS4tSPHuGibyoeqMV/TJlSKda6FXzoEyYGjTe+vXA=='),
            window.jspdf ? null : load('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', 'sha512-qZvrmS2ekKPF2mSznTQsxqPgnpkI4DNTlrdUmTzrDgektczlKNRRhy5X5AAOnx5S09ydFYWWNSfcEqDTTHgtNA=='),
        ]);
        return libs;
    }
    async function prepare() {
        await ready();
        if (document.fonts) await document.fonts.ready;
    }

    // html2canvas draws SVGs as images without the page's CSS: write the
    // colours into the SVG and drop the other templates' artwork first.
    function bake(root) {
        root.querySelectorAll('svg').forEach(function (svg) {
            if (getComputedStyle(svg).display === 'none') { svg.remove(); return; }
            svg.querySelectorAll('*').forEach(function (el) {
                if (!el.isConnected) return;
                var cs = getComputedStyle(el);
                if (cs.display === 'none') { el.remove(); return; }
                if (el.getAttribute('class') && cs.fill && cs.fill !== 'none') el.setAttribute('fill', cs.fill);
            });
        });
    }

    // One side as an image, from an unscaled copy off screen (~385 dpi;
    // more makes html2canvas hang). Square corners when it's cut from paper.
    async function shot(el, square) {
        var holder = document.createElement('div');
        holder.style.cssText = 'position:fixed; left:-10000px; top:0;';
        var copy = el.cloneNode(true);
        copy.style.transform = 'none'; copy.style.boxShadow = 'none';
        if (square) copy.style.borderRadius = '0';
        holder.appendChild(copy);
        document.body.appendChild(holder);
        try {
            bake(copy);
            var r = copy.getBoundingClientRect();
            var canvas = await window.html2canvas(copy, { scale: 4, backgroundColor: square ? '#ffffff' : null, logging: false, useCORS: true });
            // html2canvas rounds the size up, leaving a blank pixel row/column on the
            // right and bottom: cut the picture back to the card itself.
            var cw = Math.floor(r.width * 4), ch = Math.floor(r.height * 4);
            if (canvas.width > cw || canvas.height > ch) {
                var exact = document.createElement('canvas');
                exact.width = cw; exact.height = ch;
                exact.getContext('2d').drawImage(canvas, 0, 0);
                canvas = exact;
            }
            return canvas;
        } finally {
            holder.remove();
        }
    }

    // ---- Turning, mirroring, bleed ---------------------------------------------

    function angle(o, isBack) {
        if (o.paper === 'pvc') return (o.landscape ? -90 : 0) + (isBack && o.backFlip ? 180 : 0);
        return isBack && o.fold ? 180 : 0;
    }

    function orient(canvas, o, isBack) {
        var rot = angle(o, isBack);
        if (!rot && !o.mirror) return canvas;
        var swap = Math.abs(rot % 180) === 90;
        var c = document.createElement('canvas');
        c.width = swap ? canvas.height : canvas.width;
        c.height = swap ? canvas.width : canvas.height;
        var x = c.getContext('2d');
        x.translate(c.width / 2, c.height / 2);
        x.rotate(rot * Math.PI / 180);
        if (o.mirror) x.scale(-1, 1);
        x.drawImage(canvas, -canvas.width / 2, -canvas.height / 2);
        return c;
    }

    // Stretch the card's edge colours outward by mm (the card is W mm wide).
    // The colour is taken 2 px in from the edge, past any soft edge pixels.
    function addBleed(c, mm) {
        if (!mm) return c;
        var p = Math.round(c.width / W * mm), w = c.width, h = c.height, i = 2;
        var out = document.createElement('canvas');
        out.width = w + 2 * p; out.height = h + 2 * p;
        var x = out.getContext('2d');
        x.imageSmoothingEnabled = false;
        x.drawImage(c, i, 0, 1, h, 0, p, p, h);                       // left
        x.drawImage(c, w - 1 - i, 0, 1, h, p + w, p, p, h);           // right
        x.drawImage(c, 0, i, w, 1, p, 0, w, p);                       // top
        x.drawImage(c, 0, h - 1 - i, w, 1, p, p + h, w, p);           // bottom
        x.drawImage(c, i, i, 1, 1, 0, 0, p, p);                       // corners
        x.drawImage(c, w - 1 - i, i, 1, 1, p + w, 0, p, p);
        x.drawImage(c, i, h - 1 - i, 1, 1, 0, p + h, p, p);
        x.drawImage(c, w - 1 - i, h - 1 - i, 1, 1, p + w, p + h, p, p);
        x.drawImage(c, p, p);
        return out;
    }

    // ---- A4 layout -------------------------------------------------------------

    /**
     * Where everything goes on an A4 sheet (mm). x(slot) is a card's left
     * trim edge; front from yf, back from yb; foldY the fold line.
     */
    function sheetLayout(o) {
        var b = +o.bleed || 0;
        var colGap = b ? Math.max(0.6, 3 - 2 * b) : 3;
        var pitch = W + 2 * b + colGap;
        var x0 = (PAGE_W - (PER_PAGE * (W + 2 * b) + (PER_PAGE - 1) * colGap)) / 2 + b;
        var gap = o.fold ? Math.max(+o.foldGap || 10, 2 * b + 2) : Math.max(ROW_GAP, 2 * b + 4);
        var yf = (PAGE_H - (2 * H + gap + 2 * b)) / 2 + b;
        var yb = yf + H + gap;
        return {
            bleed: b, pitch: pitch, x0: x0, gap: gap, yf: yf, yb: yb, foldY: yf + H + gap / 2,
            x: function (slot) { return x0 + slot * pitch; },
            backDeg: o.fold ? 180 : 0,
        };
    }

    function note(o) {
        return 'Sunlit Network DC — office ID cards · '
            + (o.fold ? 'fold on the dark dashed line, then cut / die-cut on the marks' : 'top: front, below: back · cut on the marks')
            + ' · print at 100% (actual size)' + (o.mirror ? ' · MIRRORED' : '');
    }

    /**
     * The guides for one page, as line segments [x1, y1, x2, y2, kind]:
     * kind "cut" (dashed box, no bleed), "crop" (marks in the margins) or
     * "fold" (the dark dashed fold line).
     */
    function guides(L, slots, o) {
        var g = [], b = L.bleed, rows = [[L.yf, L.yf + H], [L.yb, L.yb + H]];
        slots.forEach(function (slot) {
            var x = L.x(slot);
            if (!b) {
                // Dashed cut box: one round the front + back when they're folded, else one each.
                (o.fold ? [[L.yf, L.yb + H]] : rows).forEach(function (r) {
                    g.push([x, r[0], x + W, r[0], 'cut'], [x + W, r[0], x + W, r[1], 'cut'], [x + W, r[1], x, r[1], 'cut'], [x, r[1], x, r[0], 'cut']);
                });
            }
            // Crop marks above the top row and below the bottom row.
            [x, x + W].forEach(function (cx) {
                g.push([cx, L.yf - b - 1, cx, L.yf - b - 4, 'crop'], [cx, L.yb + H + b + 1, cx, L.yb + H + b + 4, 'crop']);
            });
            if (o.fold) g.push([x, L.foldY, x + W, L.foldY, 'fold']);
        });
        // Crop marks in the side margins, level with the card edges.
        var left = L.x(0) - b, right = L.x(slots[slots.length - 1]) + W + b;
        [L.yf, L.yf + H, L.yb, L.yb + H].forEach(function (y) {
            g.push([left - 1, y, Math.max(left - 4, 0.5), y, 'crop'], [right + 1, y, Math.min(right + 4, PAGE_W - 0.5), y, 'crop']);
        });
        if (o.fold) {
            g.push([Math.max(left - 5, 0.5), L.foldY, left - 0.6, L.foldY, 'fold'], [right + 0.6, L.foldY, Math.min(right + 5, PAGE_W - 0.5), L.foldY, 'fold']);
        }
        return g;
    }

    function drawGuides(pdf, list) {
        list.forEach(function (s) {
            if (s[4] === 'cut') { pdf.setDrawColor(100, 116, 139); pdf.setLineWidth(0.2); pdf.setLineDashPattern([1.2, 0.8], 0); }
            else if (s[4] === 'fold') { pdf.setDrawColor(15, 23, 42); pdf.setLineWidth(0.3); pdf.setLineDashPattern([1.6, 0.9], 0); }
            else { pdf.setDrawColor(15, 23, 42); pdf.setLineWidth(0.15); pdf.setLineDashPattern([], 0); }
            pdf.line(s[0], s[1], s[2], s[3]);
        });
        pdf.setLineDashPattern([], 0);
    }

    function guidesHtml(list) {
        return list.map(function (s) {
            var horizontal = s[1] === s[3];
            var style = s[4] === 'cut' ? '.2mm dashed #64748b' : s[4] === 'fold' ? '.3mm dashed #0f172a' : '.15mm solid #0f172a';
            var left = Math.min(s[0], s[2]), top = Math.min(s[1], s[3]);
            return '<div style="position:absolute; left:' + left + 'mm; top:' + top + 'mm; '
                + (horizontal ? 'width:' + Math.abs(s[2] - s[0]) + 'mm; height:0; border-top:' : 'height:' + Math.abs(s[3] - s[1]) + 'mm; width:0; border-left:') + style + '"></div>';
        }).join('');
    }

    // ---- PVC: which sides, in what order -----------------------------------------

    function sidesOf(pairs, o) {
        var out = [];
        if (o.sides === 'front') pairs.forEach(function (p) { out.push({ el: p.front, back: false }); });
        else if (o.sides === 'back') pairs.forEach(function (p) { out.push({ el: p.back, back: true }); });
        else if (o.order === 'grouped') {
            pairs.forEach(function (p) { out.push({ el: p.front, back: false }); });
            pairs.forEach(function (p) { out.push({ el: p.back, back: true }); });
        } else pairs.forEach(function (p) { out.push({ el: p.front, back: false }, { el: p.back, back: true }); });
        return out;
    }

    function options(opts) {
        var o = Object.assign({}, DEFAULTS, opts || {});
        o.bleed = o.paper === 'a4' ? +o.bleed || 0 : 0;
        o.foldGap = +o.foldGap || DEFAULTS.foldGap;
        return o;
    }

    var IdSheet = {
        W: W, H: H, PER_PAGE: PER_PAGE,

        layout: function (opts) { return sheetLayout(options(opts)); },

        /** PDF: A4 sheets, or one card-size page per side. */
        pdf: async function (pairs, fileName, opts, onProgress) {
            var o = options(opts);
            await prepare();
            var pdf;
            if (o.paper === 'pvc') {
                var list = sidesOf(pairs, o);
                var fmt = o.landscape ? [H, W] : [W, H];
                pdf = new window.jspdf.jsPDF({ unit: 'mm', format: fmt, orientation: o.landscape ? 'landscape' : 'portrait', compress: true });
                for (var k = 0; k < list.length; k++) {
                    if (onProgress) onProgress(k + 1, list.length);
                    if (k) pdf.addPage(fmt, o.landscape ? 'landscape' : 'portrait');
                    var img = orient(await shot(list[k].el, true), o, list[k].back);
                    pdf.addImage(img.toDataURL('image/jpeg', 0.95), 'JPEG', 0, 0, fmt[0], fmt[1]);
                }
                pdf.save(fileName + '.pdf');
                return;
            }

            var L = sheetLayout(o), b = L.bleed;
            pdf = new window.jspdf.jsPDF({ unit: 'mm', format: 'a4', orientation: 'landscape', compress: true });
            for (var start = 0; start < pairs.length; start += PER_PAGE) {
                if (start) pdf.addPage('a4', 'landscape');
                var slots = [];
                for (var i = start; i < Math.min(start + PER_PAGE, pairs.length); i++) {
                    var slot = i - start, x = L.x(slot);
                    slots.push(slot);
                    if (onProgress) onProgress(i + 1, pairs.length);
                    var front = addBleed(orient(await shot(pairs[i].front, true), o, false), b);
                    var back = addBleed(orient(await shot(pairs[i].back, true), o, true), b);
                    pdf.addImage(front.toDataURL('image/jpeg', 0.95), 'JPEG', x - b, L.yf - b, W + 2 * b, H + 2 * b);
                    pdf.addImage(back.toDataURL('image/jpeg', 0.95), 'JPEG', x - b, L.yb - b, W + 2 * b, H + 2 * b);
                }
                drawGuides(pdf, guides(L, slots, o));
                if (o.fold) { pdf.setFontSize(6); pdf.setTextColor(15, 23, 42); pdf.text('FOLD', 1, L.foldY - 0.8); }
                pdf.setFontSize(7); pdf.setTextColor(100, 116, 139);
                pdf.text(note(o), PAGE_W / 2, PAGE_H - 3, { align: 'center' });
            }
            pdf.save(fileName + '.pdf');
        },

        /** Card-size PDF (kept for older calls). */
        cardPdf: function (pair, fileName, opts) {
            return IdSheet.pdf([pair], fileName, Object.assign({}, opts || {}, { paper: 'pvc' }));
        },

        /** Both sides as PNG files (mirrored / turned as asked). */
        png: async function (pair, fileName, opts) {
            var o = options(Object.assign({}, opts || {}, { paper: 'pvc', landscape: false, backFlip: false }));
            await prepare();
            var sides = [orient(await shot(pair.front, false), o, false), orient(await shot(pair.back, false), o, true)];
            sides.forEach(function (canvas, i) {
                var a = document.createElement('a');
                a.href = canvas.toDataURL('image/png');
                a.download = fileName + (i ? ' - back' : ' - front') + (o.mirror ? ' (mirror)' : '') + '.png';
                document.body.appendChild(a); a.click(); a.remove();
            });
        },

        /**
         * Print in its own window: the HTML itself (sharpest), or — for A4
         * with bleed — the cards as images with their extra colour.
         */
        print: function (pairs, title, opts) {
            var o = typeof opts === 'string' ? options({ paper: opts === 'card' ? 'pvc' : 'a4' }) : options(opts);
            var css = document.getElementById('idc-css').outerHTML;
            var w = window.open('', '_blank');
            if (!w) return false;
            w.document.write('<p style="font:15px Arial, sans-serif; padding:24px; color:#334155">Preparing the cards…</p>');

            var plain = function (el, deg) {
                var c = el.cloneNode(true);
                c.style.boxShadow = 'none'; c.style.borderRadius = '0';
                c.style.transformOrigin = '50% 50%';
                c.style.transform = ((deg ? 'rotate(' + deg + 'deg) ' : '') + (o.mirror ? 'scaleX(-1)' : '')) || 'none';
                return c.outerHTML;
            };

            (async function () {
                var body = '', page;
                if (o.paper === 'pvc') {
                    var pw = o.landscape ? H : W, ph = o.landscape ? W : H;
                    page = '@page { size: ' + pw + 'mm ' + ph + 'mm; margin: 0; } html, body { margin: 0; padding: 0; }'
                        + '.pv { position: relative; width: ' + pw + 'mm; height: ' + ph + 'mm; overflow: hidden; page-break-after: always; break-after: page; }'
                        + '.pv:last-child { page-break-after: auto; break-after: auto; }'
                        + '.pv > .idc { position: absolute; left: ' + (pw - W) / 2 + 'mm; top: ' + (ph - H) / 2 + 'mm; }'
                        + '@media screen { body { background: #e2e8f0; } .pv { background: #fff; margin: 6mm auto; box-shadow: 0 2px 10px rgba(0,0,0,.2); } }';
                    sidesOf(pairs, o).forEach(function (side) { body += '<div class="pv">' + plain(side.el, angle(o, side.back)) + '</div>'; });
                } else {
                    var L = sheetLayout(o), b = L.bleed;
                    if (b) await prepare();
                    page = '@page { size: A4 landscape; margin: 0; } html, body { margin: 0; padding: 0; }'
                        + '.sheet { position: relative; width: ' + PAGE_W + 'mm; height: ' + PAGE_H + 'mm; overflow: hidden; page-break-after: always; break-after: page; }'
                        + '.sheet:last-child { page-break-after: auto; break-after: auto; }'
                        + '.panel { position: absolute; width: ' + (W + 2 * b) + 'mm; height: ' + (H + 2 * b) + 'mm; }'
                        + '.panel > img { display: block; width: 100%; height: 100%; }'
                        + '.foldtxt { position: absolute; left: 1mm; font: 700 6pt Arial, sans-serif; color: #0f172a; }'
                        + '.note { position: absolute; left: 0; right: 0; bottom: 2.5mm; text-align: center; font: 7pt Arial, sans-serif; color: #64748b; }'
                        + '@media screen { body { background: #e2e8f0; } .sheet { background: #fff; margin: 6mm auto; box-shadow: 0 2px 10px rgba(0,0,0,.2); } }';
                    for (var start = 0; start < pairs.length; start += PER_PAGE) {
                        body += '<div class="sheet">';
                        var slots = [];
                        for (var i = start; i < Math.min(start + PER_PAGE, pairs.length); i++) {
                            var slot = i - start, x = L.x(slot), p = pairs[i];
                            slots.push(slot);
                            var front, back;
                            if (b) {
                                front = '<img alt="" src="' + addBleed(orient(await shot(p.front, true), o, false), b).toDataURL('image/jpeg', 0.95) + '">';
                                back = '<img alt="" src="' + addBleed(orient(await shot(p.back, true), o, true), b).toDataURL('image/jpeg', 0.95) + '">';
                            } else {
                                front = plain(p.front, 0);
                                back = plain(p.back, L.backDeg);
                            }
                            body += '<div class="panel" style="left:' + (x - b) + 'mm; top:' + (L.yf - b) + 'mm">' + front + '</div>'
                                + '<div class="panel" style="left:' + (x - b) + 'mm; top:' + (L.yb - b) + 'mm">' + back + '</div>';
                        }
                        body += guidesHtml(guides(L, slots, o));
                        if (o.fold) body += '<div class="foldtxt" style="top:' + (L.foldY - 3) + 'mm">FOLD</div>';
                        body += '<div class="note">' + note(o) + '</div></div>';
                    }
                }
                w.document.open();
                w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>' + title + '</title>'
                    + '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">'
                    + css + '<style>' + page + '</style></head><body>' + body + '</body></html>');
                w.document.close();
                await (w.document.fonts ? w.document.fonts.ready : Promise.resolve());
                setTimeout(function () { w.focus(); w.print(); }, 500);
            })().catch(function (e) {
                w.document.body.textContent = 'Could not prepare the cards: ' + e.message;
            });
            return true;
        },

        /**
         * The print options form inside root ([data-opt] fields): shows only
         * what applies to the chosen paper, remembers the choice in this
         * browser, and returns a function that reads the current options.
         */
        bindOptions: function (root) {
            var KEY = 'idcard-print-options';
            var read = function () {
                var o = {};
                root.querySelectorAll('[data-opt]').forEach(function (el) {
                    var name = el.dataset.opt;
                    if (el.type === 'radio') { if (el.checked) o[name] = el.value; }
                    else if (el.type === 'checkbox') o[name] = el.checked;
                    else o[name] = el.value;
                });
                o.copies = +o.copies || 1;
                o.landscape = o.orientation === 'landscape';
                return options(o);
            };
            var refresh = function () {
                var o = read();
                root.querySelectorAll('[data-for]').forEach(function (el) {
                    var show = el.dataset.for.split(' ').indexOf(o.paper) !== -1;
                    if (show && el.dataset.when === 'both') show = o.sides === 'both';
                    if (show && el.dataset.when === 'fold') show = !!o.fold;
                    el.style.display = show ? '' : 'none';
                });
                try { localStorage.setItem(KEY, JSON.stringify(o)); } catch (e) { /* private window */ }
            };
            try {
                var saved = JSON.parse(localStorage.getItem(KEY) || 'null');
                if (saved) root.querySelectorAll('[data-opt]').forEach(function (el) {
                    var v = saved[el.dataset.opt];
                    if (v === undefined) return;
                    if (el.type === 'radio') el.checked = el.value === String(v);
                    else if (el.type === 'checkbox') el.checked = !!v;
                    else if (el.dataset.opt !== 'copies') el.value = String(v);
                });
            } catch (e) { /* nothing saved */ }
            root.addEventListener('change', refresh);
            refresh();
            return read;
        },
    };

    window.IdSheet = IdSheet;
})();
