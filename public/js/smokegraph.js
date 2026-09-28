/* ==========================================================================
   SmokeGraph — SmokePing-style latency graph drawn on a <canvas>.

   Data comes from LatencyController@data:
     { start, end, step, pings, points: [[ts, median, lossPct, [rtt...]], ...] }

   Per bucket:
     - grey "smoke": nested bands between the i-th fastest and i-th slowest
       ping, stacked so the dense middle of the distribution is darkest;
     - a median line coloured by packet loss (green = none, red = heavy);
     - a pale red column when every ping was lost.

   Usage:  SmokeGraph.create(element, { url, height, compact, refresh })
   ========================================================================== */

(function (window) {
    'use strict';

    var LOSS_COLORS = [
        { max: 0,   color: '#22c55e', label: '0' },
        { max: 5,   color: '#00b8ff', label: '≤5%' },
        { max: 10,  color: '#0059ff', label: '≤10%' },
        { max: 15,  color: '#5e00ff', label: '≤15%' },
        { max: 25,  color: '#9d00ff', label: '≤25%' },
        { max: 50,  color: '#dd00ff', label: '≤50%' },
        { max: 100, color: '#ff0000', label: '>50%' },
    ];

    var TIME_STEPS = [300, 600, 900, 1800, 3600, 7200, 10800, 21600, 43200, 86400, 172800, 604800];
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    function lossColor(loss) {
        for (var i = 0; i < LOSS_COLORS.length; i++) {
            if (loss <= LOSS_COLORS[i].max) return LOSS_COLORS[i].color;
        }
        return LOSS_COLORS[LOSS_COLORS.length - 1].color;
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    var ALERT_RED = '#dc2626';

    // Does a bucket break the target's thresholds? Mirrors
    // LatencyTarget::breaches() on the server.
    function breaches(p, d) {
        if (d.loss_threshold !== null && d.loss_threshold !== undefined && p[2] >= d.loss_threshold) return true;
        if (d.threshold === null || d.threshold === undefined) return false;
        return p[1] === null ? p[2] >= 100 : p[1] > d.threshold;
    }

    function fmtMs(v) {
        if (v === null || v === undefined || isNaN(v)) return '—';
        return (v >= 100 ? v.toFixed(0) : v >= 10 ? v.toFixed(1) : v.toFixed(2)) + ' ms';
    }

    function fmtTime(ts, withDate) {
        var d = new Date(ts * 1000);
        var hm = pad(d.getHours()) + ':' + pad(d.getMinutes());
        return withDate ? d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + hm : hm;
    }

    function niceStep(range, ticks) {
        var raw = range / ticks;
        var mag = Math.pow(10, Math.floor(Math.log10(raw)));
        var norm = raw / mag;
        return (norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 2.5 ? 2.5 : norm <= 5 ? 5 : 10) * mag;
    }

    function SmokeGraph(el, opts) {
        this.el = el;
        this.opts = Object.assign({ height: 260, compact: false, refresh: 60 }, opts || {});
        this.data = null;

        el.classList.add('smokegraph');
        el.innerHTML =
            '<div class="smokegraph-canvas-wrap">' +
                '<canvas></canvas>' +
                '<div class="smokegraph-tip" hidden></div>' +
                '<div class="smokegraph-msg">Loading…</div>' +
            '</div>' +
            (this.opts.compact ? '' : '<div class="smokegraph-stats"></div>');

        this.wrap = el.querySelector('.smokegraph-canvas-wrap');
        this.canvas = el.querySelector('canvas');
        this.tip = el.querySelector('.smokegraph-tip');
        this.msg = el.querySelector('.smokegraph-msg');
        this.stats = el.querySelector('.smokegraph-stats');
        this.wrap.style.height = this.opts.height + 'px';

        var self = this;

        this.canvas.addEventListener('mousemove', function (e) { self.hover(e); });
        this.canvas.addEventListener('mouseleave', function () { self.tip.hidden = true; self.draw(); });

        if (window.ResizeObserver) {
            new ResizeObserver(function () { self.draw(); }).observe(this.wrap);
        } else {
            window.addEventListener('resize', function () { self.draw(); });
        }

        this.load();

        if (this.opts.refresh) {
            setInterval(function () { if (!document.hidden) self.load(); }, this.opts.refresh * 1000);
        }
    }

    SmokeGraph.prototype.load = function () {
        var self = this;

        fetch(this.opts.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                self.data = data;
                self.prepare();
                self.draw();
                self.renderStats();
            })
            .catch(function () {
                self.msg.textContent = 'Could not load graph data';
                self.msg.hidden = false;
            });
    };

    // Work out the y-axis ceiling once per load. Uses each bucket's 90th
    // percentile ping instead of the absolute max so one huge spike doesn't
    // flatten the rest of the graph (anything taller is clipped).
    SmokeGraph.prototype.prepare = function () {
        var pts = this.data.points;
        var top = 0;

        pts.forEach(function (p) {
            var smoke = p[3];
            if (smoke.length) top = Math.max(top, smoke[Math.floor((smoke.length - 1) * 0.9)]);
            if (p[1] !== null) top = Math.max(top, p[1] * 1.3);
        });

        var thr = this.data.threshold;
        if (thr !== null && thr !== undefined && thr <= Math.max(top, 1) * 4) {
            top = Math.max(top, thr * 1.1);
        }

        top = Math.max(top * 1.1, 1);
        this.yStep = niceStep(top, this.opts.compact ? 3 : 5);
        this.yMax = Math.ceil(top / this.yStep) * this.yStep;
    };

    SmokeGraph.prototype.layout = function () {
        var c = this.opts.compact;
        var w = this.wrap.clientWidth;
        var h = this.opts.height;

        return {
            w: w,
            h: h,
            left: c ? 38 : 62,
            right: c ? 6 : 14,
            top: c ? 6 : 10,
            bottom: c ? 18 : 26,
        };
    };

    SmokeGraph.prototype.draw = function (hoverIndex) {
        if (!this.data) return;

        var d = this.data;
        var L = this.layout();
        var dpr = window.devicePixelRatio || 1;
        var canvas = this.canvas;

        canvas.width = Math.round(L.w * dpr);
        canvas.height = Math.round(L.h * dpr);
        canvas.style.width = L.w + 'px';
        canvas.style.height = L.h + 'px';

        var ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, L.w, L.h);

        var plotW = L.w - L.left - L.right;
        var plotH = L.h - L.top - L.bottom;
        var span = d.end - d.start;
        var yMax = this.yMax;
        var compact = this.opts.compact;

        var x = function (ts) { return L.left + (ts - d.start) / span * plotW; };
        var y = function (ms) { return L.top + plotH - Math.min(ms, yMax) / yMax * plotH; };
        var colW = Math.max(1, d.step / span * plotW);

        this.geom = { L: L, plotW: plotW, span: span };

        // Plot background + horizontal grid
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(L.left, L.top, plotW, plotH);

        ctx.font = (compact ? 10 : 11) + 'px Inter, system-ui, sans-serif';
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'right';
        ctx.lineWidth = 1;

        for (var v = 0; v <= yMax + 1e-9; v += this.yStep) {
            var gy = Math.round(y(v)) + 0.5;
            ctx.strokeStyle = v === 0 ? '#cbd5e1' : '#eef2f7';
            ctx.beginPath();
            ctx.moveTo(L.left, gy);
            ctx.lineTo(L.left + plotW, gy);
            ctx.stroke();
            ctx.fillStyle = '#64748b';
            ctx.fillText(+v.toFixed(2) + (compact ? '' : ' ms'), L.left - 6, gy);
        }

        // Vertical time grid
        var target = compact ? 4 : Math.max(4, Math.floor(plotW / 110));
        var tStep = TIME_STEPS[TIME_STEPS.length - 1];
        for (var i = 0; i < TIME_STEPS.length; i++) {
            if (span / TIME_STEPS[i] <= target) { tStep = TIME_STEPS[i]; break; }
        }

        var tzOffset = new Date().getTimezoneOffset() * 60;
        var first = Math.ceil((d.start - tzOffset) / tStep) * tStep + tzOffset;
        var withDate = tStep >= 86400;

        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';

        for (var t = first; t <= d.end; t += tStep) {
            var gx = Math.round(x(t)) + 0.5;
            ctx.strokeStyle = '#eef2f7';
            ctx.beginPath();
            ctx.moveTo(gx, L.top);
            ctx.lineTo(gx, L.top + plotH);
            ctx.stroke();
            ctx.fillStyle = '#64748b';
            ctx.fillText(withDate ? fmtTime(t, true).split(' ').slice(0, 2).join(' ') : fmtTime(t), gx, L.top + plotH + (compact ? 4 : 7));
        }

        if (!d.points.length) {
            this.msg.textContent = 'No data yet — waiting for the first probe';
            this.msg.hidden = false;
            return;
        }
        this.msg.hidden = true;

        ctx.save();
        ctx.beginPath();
        ctx.rect(L.left, L.top, plotW, plotH);
        ctx.clip();

        d.points.forEach(function (p) {
            var x0 = x(p[0]);
            var smoke = p[3];
            var n = smoke.length;
            var over = breaches(p, d);

            // Over threshold: tint the whole column red
            if (over && p[2] < 100) {
                ctx.fillStyle = 'rgba(239, 68, 68, 0.13)';
                ctx.fillRect(x0, L.top, colW, plotH);
            }

            // Total loss: no replies at all in this bucket
            if (p[2] >= 100) {
                ctx.fillStyle = 'rgba(239, 68, 68, 0.18)';
                ctx.fillRect(x0, L.top, colW, plotH);
                return;
            }

            // Smoke — nested bands, darkest where most pings landed
            var layers = Math.floor(n / 2);
            ctx.fillStyle = 'rgba(15, 23, 42, ' + (0.55 / Math.max(1, layers)).toFixed(3) + ')';
            for (var k = 0; k < layers; k++) {
                var yTop = y(smoke[n - 1 - k]);
                var yBot = y(smoke[k]);
                ctx.fillRect(x0, yTop, colW, Math.max(1, yBot - yTop));
            }

            // Median, coloured by loss — or red when over the threshold
            if (p[1] !== null) {
                ctx.fillStyle = over ? ALERT_RED : lossColor(p[2]);
                ctx.fillRect(x0, y(p[1]) - (compact ? 1 : 1.25), Math.max(colW, 1.5), compact ? 2 : (over ? 3 : 2.5));
            }
        });

        ctx.restore();

        // Threshold line
        if (d.threshold !== null && d.threshold !== undefined) {
            ctx.font = (compact ? 9 : 11) + 'px Inter, system-ui, sans-serif';
            ctx.fillStyle = ALERT_RED;
            ctx.textAlign = 'right';

            if (d.threshold <= yMax) {
                var ty = Math.round(y(d.threshold)) + 0.5;
                ctx.strokeStyle = ALERT_RED;
                ctx.lineWidth = compact ? 1 : 1.25;
                ctx.setLineDash([6, 4]);
                ctx.beginPath();
                ctx.moveTo(L.left, ty);
                ctx.lineTo(L.left + plotW, ty);
                ctx.stroke();
                ctx.setLineDash([]);
                ctx.lineWidth = 1;
                ctx.textBaseline = 'bottom';
                ctx.fillText((compact ? '' : 'threshold ') + d.threshold + ' ms', L.left + plotW - 4, ty - 2);
            } else if (!compact) {
                ctx.textBaseline = 'top';
                ctx.fillText('threshold ' + d.threshold + ' ms ↑ (above graph)', L.left + plotW - 4, L.top + 3);
            }
        }

        // Hover crosshair
        if (hoverIndex !== undefined && d.points[hoverIndex]) {
            var hx = Math.round(x(d.points[hoverIndex][0]) + colW / 2) + 0.5;
            ctx.strokeStyle = 'rgba(79, 70, 229, 0.6)';
            ctx.setLineDash([3, 3]);
            ctx.beginPath();
            ctx.moveTo(hx, L.top);
            ctx.lineTo(hx, L.top + plotH);
            ctx.stroke();
            ctx.setLineDash([]);
        }

        // Frame
        ctx.strokeStyle = '#cbd5e1';
        ctx.strokeRect(L.left + 0.5, L.top + 0.5, plotW - 1, plotH - 1);
    };

    SmokeGraph.prototype.hover = function (e) {
        if (!this.data || !this.data.points.length || !this.geom) return;

        var rect = this.canvas.getBoundingClientRect();
        var mx = e.clientX - rect.left;
        var g = this.geom;
        var d = this.data;

        if (mx < g.L.left || mx > g.L.left + g.plotW) {
            this.tip.hidden = true;
            this.draw();
            return;
        }

        var ts = d.start + (mx - g.L.left) / g.plotW * g.span;

        // Nearest bucket that actually has data
        var best = -1, bestDist = Infinity;
        for (var i = 0; i < d.points.length; i++) {
            var dist = Math.abs(d.points[i][0] + d.step / 2 - ts);
            if (dist < bestDist) { bestDist = dist; best = i; }
        }

        if (best < 0 || bestDist > d.step * 3) {
            this.tip.hidden = true;
            this.draw();
            return;
        }

        var p = d.points[best];
        var smoke = p[3];
        var withDate = g.span > 86400;

        this.tip.innerHTML =
            '<div class="smokegraph-tip-time">' + fmtTime(p[0], withDate) +
                (d.step > 60 ? ' – ' + fmtTime(p[0] + d.step, false) : '') + '</div>' +
            '<div><span class="smokegraph-dot" style="background:' + lossColor(p[2]) + '"></span>' +
                'Median <b>' + fmtMs(p[1]) + '</b></div>' +
            '<div>Loss <b>' + p[2] + '%</b></div>' +
            (breaches(p, d) ? '<div style="color:#fca5a5"><b>⚠ Over threshold</b></div>' : '') +
            (smoke.length ? '<div>Min / Max <b>' + fmtMs(smoke[0]) + '</b> / <b>' + fmtMs(smoke[smoke.length - 1]) + '</b></div>' : '');

        this.tip.hidden = false;

        var tipW = this.tip.offsetWidth;
        var left = mx + 14;
        if (left + tipW > this.wrap.clientWidth) left = mx - tipW - 14;
        this.tip.style.left = left + 'px';
        this.tip.style.top = (g.L.top + 6) + 'px';

        this.draw(best);
    };

    SmokeGraph.prototype.renderStats = function () {
        var el = this.stats;
        var pts = this.data.points;

        if (this.opts.onData) this.opts.onData(this.data);
        if (!el) return;

        var medians = pts.map(function (p) { return p[1]; }).filter(function (v) { return v !== null; });
        var losses = pts.map(function (p) { return p[2]; });
        var last = pts[pts.length - 1];

        var avg = function (a) { return a.length ? a.reduce(function (s, v) { return s + v; }, 0) / a.length : null; };
        var max = function (a) { return a.length ? Math.max.apply(null, a) : null; };
        var min = function (a) { return a.length ? Math.min.apply(null, a) : null; };
        var pct = function (v) { return v === null ? '—' : (+v.toFixed(2)) + '%'; };

        var d = this.data;
        var hasThr = (d.threshold !== null && d.threshold !== undefined) || (d.loss_threshold !== null && d.loss_threshold !== undefined);
        var overCount = pts.filter(function (p) { return breaches(p, d); }).length;
        var thrRow = hasThr
            ? '<div class="smokegraph-stat-row">' +
                '<span class="smokegraph-stat-label">Threshold</span>' +
                (d.threshold !== null && d.threshold !== undefined ? '<span>latency &gt; <b>' + d.threshold + ' ms</b></span>' : '') +
                (d.loss_threshold !== null && d.loss_threshold !== undefined ? '<span>loss ≥ <b>' + d.loss_threshold + '%</b></span>' : '') +
                '<span' + (overCount ? ' style="color:' + ALERT_RED + '"' : '') + '>over for <b>' +
                    (pts.length ? +(overCount / pts.length * 100).toFixed(1) : 0) + '%</b> of this period</span>' +
              '</div>'
            : '';

        var legend = LOSS_COLORS.map(function (c) {
            return '<span class="smokegraph-legend-item"><span class="smokegraph-dot" style="background:' + c.color + '"></span>' + c.label + '</span>';
        }).join('');

        el.innerHTML =
            '<div class="smokegraph-stat-row">' +
                '<span class="smokegraph-stat-label">Median RTT</span>' +
                '<span>now <b>' + fmtMs(last ? last[1] : null) + '</b></span>' +
                '<span>avg <b>' + fmtMs(avg(medians)) + '</b></span>' +
                '<span>min <b>' + fmtMs(min(medians)) + '</b></span>' +
                '<span>max <b>' + fmtMs(max(medians)) + '</b></span>' +
            '</div>' +
            '<div class="smokegraph-stat-row">' +
                '<span class="smokegraph-stat-label">Packet Loss</span>' +
                '<span>now <b>' + pct(last ? last[2] : null) + '</b></span>' +
                '<span>avg <b>' + pct(avg(losses)) + '</b></span>' +
                '<span>max <b>' + pct(max(losses)) + '</b></span>' +
            '</div>' +
            thrRow +
            '<div class="smokegraph-legend">' +
                '<span class="smokegraph-stat-label">Loss color</span>' + legend +
                (hasThr ? '<span class="smokegraph-legend-item"><span class="smokegraph-dot" style="background:' + ALERT_RED + '"></span>over threshold</span>' : '') +
                '<span class="smokegraph-legend-item"><span class="smokegraph-swatch"></span>' + this.data.pings + ' pings / probe</span>' +
            '</div>';
    };

    window.SmokeGraph = {
        create: function (el, opts) { return new SmokeGraph(el, opts); },
        lossColor: lossColor,
    };

})(window);
