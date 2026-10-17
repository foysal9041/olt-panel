/**
 * Weathermap — draws a network map in SVG: nodes (switches, routers,
 * labels) and links between them as two arrows meeting in the middle,
 * each coloured by that direction's live utilisation (classic weathermap
 * scale). Refreshes from the map's /data URL; in edit mode nodes can be
 * dragged and their new places are saved.
 *
 *   Weathermap.start(containerEl, config)
 *   config: { width, height, labels: 'bps'|'percent'|'none', refresh, editing,
 *             dataUrl, positionsUrl, csrf, nodes: [...], links: [...] }
 */
window.Weathermap = (function () {
    'use strict';

    var NS = 'http://www.w3.org/2000/svg';
    var t = window.uiText || function (en) { return en; };
    var STATE = { up: ['#16a34a', '#ffffff'], down: ['#dc2626', '#fee2e2'], none: ['#64748b', '#ffffff'] };

    function el(name, attrs, parent) {
        var e = document.createElementNS(NS, name);
        Object.keys(attrs || {}).forEach(function (k) { e.setAttribute(k, attrs[k]); });
        if (parent) parent.appendChild(e);
        return e;
    }

    function bps(v) {
        if (v === null || v === undefined) return '—';
        var u = ['', 'K', 'M', 'G', 'T'], i = 0;
        while (v >= 1000 && i < u.length - 1) { v /= 1000; i++; }
        return (i === 0 ? Math.round(v) : v.toFixed(v >= 100 ? 0 : v >= 10 ? 1 : 2)) + u[i];
    }

    /** Arrow polygon from (x1,y1) with its tip at (x2,y2). */
    function arrow(x1, y1, x2, y2, w, hw, hl) {
        var dx = x2 - x1, dy = y2 - y1, len = Math.sqrt(dx * dx + dy * dy) || 1;
        var ux = dx / len, uy = dy / len, px = -uy, py = ux;
        hl = Math.min(hl, len * 0.6);
        var bx = x2 - ux * hl, by = y2 - uy * hl;
        var pts = [
            [x1 + px * w / 2, y1 + py * w / 2], [bx + px * w / 2, by + py * w / 2], [bx + px * hw / 2, by + py * hw / 2],
            [x2, y2],
            [bx - px * hw / 2, by - py * hw / 2], [bx - px * w / 2, by - py * w / 2], [x1 - px * w / 2, y1 - py * w / 2],
        ];
        return pts.map(function (p) { return p[0].toFixed(1) + ',' + p[1].toFixed(1); }).join(' ');
    }

    function start(container, cfg) {
        var svg = el('svg', { viewBox: '0 0 ' + cfg.width + ' ' + cfg.height, class: 'wm-svg', preserveAspectRatio: 'xMidYMid meet' }, container);
        var defs = el('defs', {}, svg);
        var pattern = el('pattern', { id: 'wm-grid', width: 20, height: 20, patternUnits: 'userSpaceOnUse' }, defs);
        el('path', { d: 'M 20 0 L 0 0 0 20', fill: 'none', stroke: '#eef2f7', 'stroke-width': 1 }, pattern);
        el('rect', { x: 0, y: 0, width: cfg.width, height: cfg.height, fill: cfg.editing ? 'url(#wm-grid)' : '#fbfdff' }, svg);
        var linkLayer = el('g', {}, svg);
        var nodeLayer = el('g', {}, svg);

        var nodes = {}, links = {}, live = { links: {}, nodes: {} };

        // Links between the same two nodes sit side by side.
        var pairs = {};
        cfg.links.forEach(function (l) {
            var key = Math.min(l.from, l.to) + '-' + Math.max(l.from, l.to);
            (pairs[key] = pairs[key] || []).push(l.id);
        });

        cfg.nodes.forEach(function (n) {
            var g = el('g', { class: 'wm-node' + (cfg.editing ? ' wm-drag' : ''), 'data-id': n.id }, nodeLayer);
            var width = Math.max(90, n.label.length * 7.2 + 44);
            var box = el('rect', { x: -width / 2, y: -17, width: width, height: 34, rx: 8, 'stroke-width': 2 }, g);
            var icon = el('text', { x: -width / 2 + 12, y: 5, class: 'wm-icon' }, g);
            icon.textContent = n.glyph;
            var label = el('text', { x: -width / 2 + 32, y: 5, class: 'wm-label' }, g);
            label.textContent = n.label;
            var title = el('title', {}, g);
            nodes[n.id] = { cfg: n, g: g, box: box, title: title, x: n.x, y: n.y };
            place(n.id);
            if (!cfg.editing && n.url) {
                g.style.cursor = 'pointer';
                g.addEventListener('click', function () { window.location = n.url; });
            }
        });

        cfg.links.forEach(function (l) {
            var g = el('g', { class: 'wm-link' }, linkLayer);
            var hit = el('line', { 'stroke-width': 22, stroke: 'transparent' }, g);
            var ab = el('polygon', { fill: '#e5e7eb', stroke: '#475569', 'stroke-width': 0.6 }, g);
            var ba = el('polygon', { fill: '#e5e7eb', stroke: '#475569', 'stroke-width': 0.6 }, g);
            var labels = [0, 1].map(function () {
                var lg = el('g', { class: 'wm-tag' }, g);
                var r = el('rect', { rx: 4, height: 16, fill: '#ffffff', stroke: '#94a3b8', 'stroke-width': 0.8 }, lg);
                var tx = el('text', { 'text-anchor': 'middle', y: 0 }, lg);
                if (cfg.labels === 'none') lg.style.display = 'none';
                return { g: lg, rect: r, text: tx };
            });
            var title = el('title', {}, g);
            var key = Math.min(l.from, l.to) + '-' + Math.max(l.from, l.to);
            links[l.id] = { cfg: l, g: g, hit: hit, ab: ab, ba: ba, labels: labels, title: title, offset: pairs[key].indexOf(l.id) - (pairs[key].length - 1) / 2 };
            if (!cfg.editing && l.url) {
                g.style.cursor = 'pointer';
                g.addEventListener('click', function () { window.location = l.url; });
            }
            drawLink(l.id);
        });

        function place(id) {
            var n = nodes[id];
            n.g.setAttribute('transform', 'translate(' + n.x + ',' + n.y + ')');
        }

        function drawLink(id) {
            var L = links[id], a = nodes[L.cfg.from], b = nodes[L.cfg.to];
            if (!a || !b) return;
            var dx = b.x - a.x, dy = b.y - a.y, len = Math.sqrt(dx * dx + dy * dy) || 1;
            var ox = -dy / len * L.offset * 26, oy = dx / len * L.offset * 26;
            var x1 = a.x + ox, y1 = a.y + oy, x2 = b.x + ox, y2 = b.y + oy, mx = (x1 + x2) / 2, my = (y1 + y2) / 2;
            L.ab.setAttribute('points', arrow(x1, y1, mx, my, 9, 20, 16));
            L.ba.setAttribute('points', arrow(x2, y2, mx, my, 9, 20, 16));
            ['x1', 'y1', 'x2', 'y2'].forEach(function (k, i) { L.hit.setAttribute(k, [x1, y1, x2, y2][i]); });
            // Labels a third of the way along each arrow, away from the nodes.
            [[x1 + (mx - x1) * 0.55, y1 + (my - y1) * 0.55], [x2 + (mx - x2) * 0.55, y2 + (my - y2) * 0.55]].forEach(function (p, i) {
                L.labels[i].g.setAttribute('transform', 'translate(' + p[0].toFixed(1) + ',' + p[1].toFixed(1) + ')');
            });
        }

        function linksOf(nodeId) {
            return Object.keys(links).filter(function (id) { return links[id].cfg.from == nodeId || links[id].cfg.to == nodeId; });
        }

        function setLabel(lbl, text) {
            lbl.text.textContent = text;
            var w = Math.max(28, text.length * 6.6 + 10);
            lbl.rect.setAttribute('x', -w / 2);
            lbl.rect.setAttribute('width', w);
            lbl.rect.setAttribute('y', -11);
            lbl.text.setAttribute('y', 1);
        }

        function paint() {
            Object.keys(links).forEach(function (id) {
                var L = links[id], d = live.links[id];
                if (!d) return;
                L.ab.setAttribute('fill', d.abColour);
                L.ba.setAttribute('fill', d.baColour);
                var fmt = function (v, pct) {
                    if (cfg.labels === 'percent') return pct === null ? '—' : Math.round(pct) + '%';
                    return bps(v);
                };
                setLabel(L.labels[0], fmt(d.ab, d.abPct));
                setLabel(L.labels[1], fmt(d.ba, d.baPct));
                var a = nodes[L.cfg.from].cfg.label, b = nodes[L.cfg.to].cfg.label;
                var line = function (x, y, v, pct) { return x + ' → ' + y + ': ' + (v === null ? '—' : bps(v) + 'bps') + (pct !== null ? ' (' + pct + '%)' : ''); };
                L.title.textContent = (L.cfg.label ? L.cfg.label + '\n' : '') +
                    line(a, b, d.ab, d.abPct) + '\n' + line(b, a, d.ba, d.baPct) +
                    (d.capacity ? '\n' + t('Capacity', 'ক্যাপাসিটি') + ' ' + bps(d.capacity) + 'bps' : '') +
                    (d.port ? '\n' + d.port : '\n' + t('No interface picked', 'ইন্টারফেস বাছাই করা হয়নি')) +
                    (d.port && !d.fresh ? '\n' + t('No recent traffic reading', 'সাম্প্রতিক ট্রাফিক রিডিং নেই') : '');
            });
            Object.keys(nodes).forEach(function (id) {
                var s = STATE[live.nodes[id] || nodes[id].cfg.state] || STATE.none;
                nodes[id].box.setAttribute('stroke', s[0]);
                nodes[id].box.setAttribute('fill', s[1]);
                var st = live.nodes[id] || nodes[id].cfg.state;
                nodes[id].title.textContent = nodes[id].cfg.label + (st === 'up' ? ' — UP' : st === 'down' ? ' — DOWN' : '');
            });
        }

        function refresh() {
            if (document.hidden) return;
            fetch(cfg.dataUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then(function (d) {
                    live = d;
                    paint();
                    var at = document.getElementById('wm-updated');
                    if (at) at.textContent = d.at;
                })
                .catch(function () {
                    var at = document.getElementById('wm-updated');
                    if (at) at.textContent = t('could not refresh', 'রিফ্রেশ করা যায়নি');
                });
        }

        paint();
        refresh();
        setInterval(refresh, Math.max(30, cfg.refresh) * 1000);

        // ---- Drag to move (edit mode) -------------------------------------
        if (cfg.editing) {
            var drag = null, pending = {}, timer = null;

            function point(ev) {
                var p = svg.createSVGPoint();
                p.x = ev.clientX; p.y = ev.clientY;
                return p.matrixTransform(svg.getScreenCTM().inverse());
            }

            function save() {
                var list = Object.keys(pending).map(function (id) { return pending[id]; });
                pending = {};
                if (!list.length) return;
                fetch(cfg.positionsUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                    body: JSON.stringify({ nodes: list }),
                }).then(function (r) {
                    var s = document.getElementById('wm-saved');
                    if (s) { s.textContent = r.ok ? t('Position saved', 'অবস্থান সেভ হয়েছে') : t('Could not save', 'সেভ করা যায়নি'); s.className = r.ok ? 'text-success small' : 'text-danger small'; }
                });
            }

            nodeLayer.addEventListener('pointerdown', function (ev) {
                var g = ev.target.closest('.wm-node');
                if (!g) return;
                var n = nodes[g.getAttribute('data-id')], p = point(ev);
                drag = { n: n, dx: p.x - n.x, dy: p.y - n.y };
                g.setPointerCapture(ev.pointerId);
                nodeLayer.appendChild(g); // on top
                ev.preventDefault();
            });
            nodeLayer.addEventListener('pointermove', function (ev) {
                if (!drag) return;
                var p = point(ev), n = drag.n;
                n.x = Math.max(10, Math.min(cfg.width - 10, Math.round((p.x - drag.dx) / 10) * 10));
                n.y = Math.max(10, Math.min(cfg.height - 10, Math.round((p.y - drag.dy) / 10) * 10));
                place(n.cfg.id);
                linksOf(n.cfg.id).forEach(drawLink);
            });
            nodeLayer.addEventListener('pointerup', function () {
                if (!drag) return;
                pending[drag.n.cfg.id] = { id: drag.n.cfg.id, x: drag.n.x, y: drag.n.y };
                drag = null;
                clearTimeout(timer);
                timer = setTimeout(save, 400);
            });
        }

        return { refresh: refresh };
    }

    return { start: start };
})();
