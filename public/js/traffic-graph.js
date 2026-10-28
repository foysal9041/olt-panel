/**
 * TrafficGraph — draws any panel graph (App\Services\GraphEngine) with
 * Chart.js 2, LibreNMS style: one line or area per series, the 95th
 * percentile, and a legend with current / average / maximum / 95th / total.
 *
 * Markup: <div class="tg" data-url="…/graph/data?type=…&id=…" data-graph="day"
 *              [data-from data-to] [data-points] [data-compact] [data-zoom]>
 *           <div class="tg-head"></div><div class="tg-plot"><canvas></canvas></div><div class="tg-legend"></div>
 *         </div>
 *
 * TrafficGraph.load(el[, graph]) · lazy(els) · setAvg('1'|'5') · png(el, name) · csvUrl(el)
 * data-zoom: drag across the graph to zoom in; el.onZoom(from, to) is called
 * (default: open data-page-url for that range).
 */
window.TrafficGraph = (function () {
    'use strict';

    var t = window.uiText || function (en) { return en; };
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var P95 = '#dc2626';
    var TITLES = { '1 Hour': '১ ঘণ্টা', '6 Hours': '৬ ঘণ্টা', '24 Hours': '২৪ ঘণ্টা', '48 Hours': '৪৮ ঘণ্টা', Weekly: 'সাপ্তাহিক', Monthly: 'মাসিক', Yearly: 'বার্ষিক', Custom: 'কাস্টম' };
    var NAMES = { In: 'ইন', Out: 'আউট', Traffic: 'ট্রাফিক', 'Traffic (all ports)': 'ট্রাফিক (সব পোর্ট)', 'Unicast packets': 'ইউনিকাস্ট প্যাকেট', 'Non-unicast packets': 'নন-ইউনিকাস্ট প্যাকেট', Errors: 'এরর', Discards: 'ডিসকার্ড', Processor: 'প্রসেসর', Memory: 'মেমরি', 'Memory used': 'মেমরি ব্যবহার', Temperature: 'তাপমাত্রা', 'Fan speed': 'ফ্যান স্পিড', Voltage: 'ভোল্টেজ', Current: 'কারেন্ট', Power: 'পাওয়ার', Uptime: 'আপটাইম', 'SNMP response time': 'SNMP রেসপন্স টাইম', Response: 'রেসপন্স' };
    var BN_DIGITS = '০১২৩৪৫৬৭৮৯';

    function tr(name) { return t(name, NAMES[name] || name); }

    /** "4 minute average", "2 hour average", "1 day average". */
    function stepLabel(step) {
        var n = step % 86400 === 0 ? [step / 86400, 'day', 'দিনের'] : step % 3600 === 0 ? [step / 3600, 'hour', 'ঘণ্টার'] : [Math.round(step / 60), 'minute', 'মিনিটের'];
        return t(n[0] + ' ' + n[1] + ' average', String(n[0]).replace(/\d/g, function (c) { return BN_DIGITS[c]; }) + ' ' + n[2] + ' গড়');
    }
    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function scaled(v, units, base) {
        var i = 0;
        while (Math.abs(v) >= base && i < units.length - 1) { v /= base; i++; }
        return (i === 0 && Math.round(v) === v ? v : v.toFixed(Math.abs(v) >= 100 ? 0 : Math.abs(v) >= 10 ? 1 : 2)) + ' ' + units[i];
    }

    /** A value in the graph's unit, e.g. bps → "1.23 Gbps", celsius → "45 °C". */
    function fmt(v, unit) {
        if (v === null || v === undefined || isNaN(v)) return '—';
        switch (unit) {
            case 'bps': return scaled(v, ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'], 1000);
            case 'pps': return scaled(v, ['pps', 'Kpps', 'Mpps', 'Gpps'], 1000);
            case 'per_s': return (v === 0 ? '0' : v < 1 ? v.toFixed(3) : v < 1000 ? v.toFixed(1) : scaled(v, ['', 'K', 'M'], 1000)) + '/s';
            case 'percent': return (v < 10 && v % 1 ? v.toFixed(1) : Math.round(v)) + '%';
            case 'celsius': return (v % 1 ? v.toFixed(1) : v) + ' °C';
            case 'volts': return v.toFixed(2) + ' V';
            case 'amps': return v.toFixed(2) + ' A';
            case 'watts': return Math.round(v) + ' W';
            case 'rpm': return Math.round(v) + ' rpm';
            case 'ms': return (v < 10 && v % 1 ? v.toFixed(1) : Math.round(v)) + ' ms';
            case 'days': return v.toFixed(v >= 100 ? 1 : 2) + ' ' + t('days', 'দিন');
            default: return '' + v;
        }
    }

    /** RRD-style short value, e.g. 4330000000 → "4.33G" (axis: "4.0 G"). */
    function short(v, unit, axis) {
        if (v === null || v === undefined || isNaN(v)) return axis ? '' : '—';
        if (['bps', 'pps'].indexOf(unit) < 0) return fmt(v, unit);
        var u = ['', 'k', 'M', 'G', 'T'], i = 0, a = Math.abs(v);
        while (a >= 1000 && i < u.length - 1) { a /= 1000; i++; }
        a = (v < 0 ? -a : a);
        return axis ? a.toFixed(1) + (u[i] ? ' ' + u[i] : '') : (i === 0 ? Math.round(a) : a.toFixed(2)) + u[i];
    }

    function total(v, unit) {
        if (v === null || v === undefined) return '—';
        return unit === 'bytes' ? scaled(v, ['B', 'KB', 'MB', 'GB', 'TB', 'PB'], 1000) : scaled(v, ['', 'K', 'M', 'G'], 1000).trim();
    }

    function clock(d) {
        var h = d.getHours();
        return pad(h % 12 || 12) + ':' + pad(d.getMinutes()) + (h >= 12 ? ' PM' : ' AM');
    }

    /**
     * X-axis labels only at round times, LibreNMS style — 09:00, 10:00 … on
     * a day, Mon, Tue … on a week, 1 Oct, 8 Oct … on a month, months on a
     * year — as many as fit the graph's width; null (no label, no grid line)
     * everywhere else.
     */
    function axisLabels(d, width, rrd) {
        var times = d.times, step = d.step, n = times.length, out = [], i;
        for (i = 0; i < n; i++) out.push(null);
        if (n < 2) return out;
        var H = 3600, D = 86400, span = times[n - 1] + step - times[0];
        var max = Math.max(3, Math.min(14, Math.floor(width / (rrd ? 62 : 78))));
        var steps = [300, 600, 900, 1800, H, 2 * H, 3 * H, 4 * H, 6 * H, 12 * H, D, 2 * D, 7 * D], every = null;
        // Over three days, whole days at least: "12:00 PM" between the dates only clutters.
        for (i = 0; i < steps.length; i++) if (steps[i] >= step && (span <= 3 * D || steps[i] >= D) && span / steps[i] <= max) { every = steps[i]; break; }
        function off(ts) { return -new Date(ts * 1000).getTimezoneOffset() * 60; }

        if (every === null) {
            // Months: every 1, 2, 3 or 6, whichever fits.
            var months = span / (30.4 * D), m = [1, 2, 3, 6].filter(function (k) { return months / k <= max; })[0] || 12;
            for (i = 1; i < n; i++) {
                var a = new Date(times[i - 1] * 1000), b = new Date(times[i] * 1000);
                if (a.getMonth() !== b.getMonth() && b.getMonth() % m === 0) out[i] = MONTHS[b.getMonth()] + (b.getMonth() === 0 ? ' ' + b.getFullYear() : '');
            }
            return out;
        }
        var align = every === 7 * D ? 4 * D : 0; // weeks start on Monday (5 Jan 1970)
        for (i = 0; i < n; i++) {
            var local = times[i] + off(times[i]);
            var mark = Math.ceil((local - align) / every) * every + align;
            if (mark >= local + step) continue;
            var t = new Date((mark - off(times[i])) * 1000), hm = pad(t.getHours()) + ':' + pad(t.getMinutes());
            if (every < D) {
                var clockText = rrd ? hm : clock(t);
                out[i] = span > D + step && hm === '00:00' ? DAYS[t.getDay()] + ' ' + t.getDate() : (span > D + step && rrd ? DAYS[t.getDay()] + ' ' + hm : clockText);
            } else {
                out[i] = span <= 8 * D ? DAYS[t.getDay()] + ' ' + t.getDate() : t.getDate() + ' ' + MONTHS[t.getMonth()];
            }
        }
        return out;
    }

    function full(ts, step) {
        var d = new Date(ts * 1000);
        var date = d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
        return step >= 86400 ? date : date + ', ' + clock(d);
    }

    function head(el, d) {
        var box = el.querySelector('.tg-head');
        if (!box || el.hasAttribute('data-compact') || el.getAttribute('data-style') === 'rrd') { if (box) box.innerHTML = ''; return; }
        box.innerHTML = '<span class="tg-title">' + (el.getAttribute('data-title') || tr(d.title)) + ' · ' + t(d.period, TITLES[d.period] || d.period) + '</span>' +
            '<span class="tg-sub">' + stepLabel(d.step) + ' · ' + full(d.start, d.step) + ' – ' + full(d.end - d.step, d.step) + '</span>';
    }

    function legend(el, d) {
        var box = el.querySelector('.tg-legend');
        if (!box) return;
        if (!d.series.length) { box.innerHTML = ''; return; }
        if (el.getAttribute('data-style') === 'rrd') { box.innerHTML = rrdLegend(d); return; }
        if (el.hasAttribute('data-compact')) {
            box.innerHTML = d.series.slice(0, 3).map(function (s) {
                return '<span><i class="tg-key" style="background:' + s.color + '"></i>' + tr(s.name) + ' <b>' + fmt(s.legend.current, d.unit) + '</b> · ' +
                    t('avg', 'গড়') + ' ' + fmt(s.legend.average, d.unit) + ' · ' + t('max', 'সর্বোচ্চ') + ' ' + fmt(s.legend.maximum, d.unit) + '</span>';
            }).join('') + (d.series.length > 3 ? '<span class="text-muted">+' + (d.series.length - 3) + '</span>' : '');
            return;
        }
        var share = function (v) { return d.capacity && v !== null && v !== undefined ? ' <small>(' + (v / d.capacity * 100).toFixed(1) + '%)</small>' : ''; };
        var withTotal = !!d.totalUnit, withP95 = d.series.some(function (s) { return s.legend.p95 !== null && s.legend.p95 !== undefined; });
        box.innerHTML = '<table class="tg-table"><thead><tr><th></th><th>' + t('Current', 'এখন') + '</th><th>' + t('Average', 'গড়') + '</th><th>' + t('Maximum', 'সর্বোচ্চ') + '</th>' +
            (withP95 ? '<th>' + t('95th %', '৯৫তম %') + '</th>' : '') + (withTotal ? '<th>' + t('Total', 'মোট') + '</th>' : '') + '</tr></thead><tbody>' +
            d.series.map(function (s) {
                var L = s.legend;
                return '<tr><th><i class="tg-key" style="background:' + s.color + '"></i>' + tr(s.name) + '</th>' +
                    '<td>' + fmt(L.current, d.unit) + share(L.current) + '</td><td>' + fmt(L.average, d.unit) + share(L.average) + '</td><td>' + fmt(L.maximum, d.unit) + share(L.maximum) + '</td>' +
                    (withP95 ? '<td>' + fmt(L.p95, d.unit) + '</td>' : '') + (withTotal ? '<td>' + total(L.total, d.totalUnit) + '</td>' : '') + '</tr>';
            }).join('') + '</tbody></table>';
    }

    /** LibreNMS-style legend: "bps Now Ave Max 95th %", a row per series, Total, Port Speed. */
    function rrdLegend(d) {
        var unitName = { bps: 'bps', pps: 'pps', per_s: '/s', percent: '%', celsius: '°C', volts: 'V', amps: 'A', watts: 'W', rpm: 'rpm', ms: 'ms', days: t('days', 'দিন') }[d.unit] || '';
        var p95 = d.series.some(function (s) { return s.legend.p95 !== null && s.legend.p95 !== undefined; });
        var rows = '<tr><th>' + unitName + '</th><th>' + t('Now', 'এখন') + '</th><th>' + t('Ave', 'গড়') + '</th><th>' + t('Max', 'সর্বোচ্চ') + '</th>' + (p95 ? '<th>95th %</th>' : '') + '</tr>';
        d.series.forEach(function (s, i) {
            var L = s.legend, c = rrdColor(d, s, i);
            rows += '<tr><td><i class="tg-key" style="background:' + c.fill + ';border:1px solid ' + c.line + '"></i>' + tr(s.name) + '</td><td>' + short(L.current, d.unit) + '</td><td>' + short(L.average, d.unit) + '</td><td>' + short(L.maximum, d.unit) + '</td>' + (p95 ? '<td>' + short(L.p95, d.unit) + '</td>' : '') + '</tr>';
        });
        var extra = '';
        if (d.totalUnit && d.series.length) {
            var sum = 0, parts = [];
            d.series.forEach(function (s) { sum += s.legend.total || 0; parts.push(tr(s.name) + ' ' + total(s.legend.total, d.totalUnit).replace(' ', '')); });
            extra += '<div>' + t('Total', 'মোট') + ' ' + total(sum, d.totalUnit).replace(' ', '') + ' &nbsp;(' + parts.join(' &nbsp;') + ')</div>';
        }
        if (d.capacity) extra += '<div><i class="tg-key" style="background:#000"></i>' + t('Port Speed', 'পোর্ট স্পিড') + ' ' + fmt(d.capacity, 'bps') + '</div>';
        return '<table class="rrd-legend">' + rows + '</table>' + extra;
    }

    /** RRD colours: In green, Out blue-violet; other series their own colour. */
    function rrdColor(d, s, i) {
        var inOut = d.series.length === 2 && (s.name === 'In' || s.name === 'Out');
        if (inOut && s.name === 'In') return { fill: 'rgba(141,183,47,0.95)', line: '#5f7f1e' };
        if (inOut && s.name === 'Out') return { fill: 'rgba(112,112,208,0.85)', line: '#3f3f9f' };
        return { fill: i === 0 && s.fill ? hexA(s.color, 0.55) : s.color, line: s.color };
    }

    function hexA(hex, a) {
        var n = parseInt(hex.slice(1), 16);
        return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')';
    }

    function draw(el, d) {
        var compact = el.hasAttribute('data-compact');
        var plot = el.querySelector('.tg-plot');
        var empty = plot.querySelector('.tg-empty');
        if (!empty) { empty = document.createElement('div'); empty.className = 'tg-empty'; plot.appendChild(empty); }
        var has = d.series.some(function (s) { return s.values.some(function (v) { return v !== null; }); });
        empty.textContent = d.series.length ? t('Nothing recorded in this period yet — it builds up from the first poll.', 'এই সময়ে এখনো কিছু রেকর্ড হয়নি — প্রথম পোল থেকে জমা হতে থাকে।') : (d.empty || t('No data.', 'ডেটা নেই।'));
        empty.hidden = has;

        var span = d.end - d.start;
        var rrd = el.getAttribute('data-style') === 'rrd';
        var mirror = rrd && el.hasAttribute('data-mirror') && d.series.length === 2;
        // Chart.js 2 draws the first dataset on top: RRD style puts Out (usually smaller) over In.
        var order = d.series.map(function (s, i) { return i; });
        if (rrd) order.reverse();
        var datasets = order.map(function (i) {
            var s = d.series[i], c = rrd ? rrdColor(d, s, i) : null;
            var values = mirror && i === 1 ? s.values.map(function (v) { return v === null ? null : -v; }) : s.values;
            return rrd ? {
                label: tr(s.name), data: values, borderColor: c.line, backgroundColor: c.fill, borderWidth: 1,
                pointRadius: 0, pointHoverRadius: 2, lineTension: 0, steppedLine: true,
                fill: (s.fill || d.series.length === 2) ? 'origin' : false, spanGaps: false,
            } : {
                label: tr(s.name), data: values, borderColor: s.color,
                backgroundColor: s.fill ? hexA(s.color, 0.3) : s.color,
                borderWidth: s.fill ? 1 : 1.5, pointRadius: 0, pointHoverRadius: 3, lineTension: 0,
                fill: s.fill && i === 0 ? 'origin' : false, spanGaps: false,
            };
        });
        var nSeries = d.series.length;
        if (rrd) {
            // 95th percentile in red: one line, or one per side when Out is mirrored.
            (mirror ? d.series : [{ legend: { p95: d.p95 } }]).forEach(function (s, i) {
                var v = s.legend.p95;
                if (v) datasets.push({ label: '95th', data: d.times.map(function () { return mirror && i === 1 ? -v : v; }), borderColor: '#ff0000', borderWidth: 1, pointRadius: 0, pointHoverRadius: 0, fill: false });
            });
        } else if (d.p95 && !compact) {
            datasets.push({ label: t('95th percentile', '৯৫তম পার্সেন্টাইল') + ' ' + fmt(d.p95, d.unit), data: d.times.map(function () { return d.p95; }), borderColor: P95, borderDash: [6, 4], borderWidth: 1, pointRadius: 0, pointHoverRadius: 0, fill: false });
        }
        var mono = { fontFamily: "'DejaVu Sans Mono', Menlo, Consolas, monospace", fontSize: 10, fontColor: '#333' };
        var labels = axisLabels(d, plot.clientWidth || 600, rrd);
        var xTicks = { autoSkip: false, maxRotation: 0, callback: function (v, i) { return labels[i]; } };
        var grid = { color: 'rgba(255,0,0,0.16)', borderDash: [1, 2], zeroLineColor: '#555', drawBorder: true };

        if (el._chart) el._chart.destroy();
        el._chart = new Chart(el.querySelector('canvas').getContext('2d'), {
            type: 'line',
            data: { labels: d.times, datasets: datasets },
            options: {
                maintainAspectRatio: false,
                animation: { duration: 0 },
                legend: { display: !compact && !rrd, position: 'bottom', labels: { boxWidth: 12, fontColor: '#475569' } },
                tooltips: {
                    mode: 'index', intersect: false,
                    filter: function (item) { return item.datasetIndex < nSeries; },
                    callbacks: {
                        title: function (items) { return items.length ? full(d.times[items[0].index], d.step) : ''; },
                        label: function (item) {
                            var s = d.series[order[item.datasetIndex]], v = s.values[item.index], peak = s.peaks ? s.peaks[item.index] : null;
                            return tr(s.name) + ' ' + fmt(v, d.unit) + (d.step > 60 && peak !== null && peak !== undefined ? '  (' + t('peak', 'সর্বোচ্চ') + ' ' + fmt(peak, d.unit) + ')' : '');
                        },
                    },
                },
                scales: rrd ? {
                    yAxes: [{
                        ticks: Object.assign({ beginAtZero: ['celsius', 'volts', 'days'].indexOf(d.unit) < 0, max: d.unit === 'percent' ? 100 : undefined, maxTicksLimit: 7, callback: function (v) { return short(v, d.unit, true); } }, mono),
                        gridLines: grid,
                    }],
                    xAxes: [{ ticks: Object.assign(xTicks, mono), gridLines: grid }],
                } : {
                    yAxes: [{
                        ticks: { beginAtZero: ['celsius', 'volts', 'days'].indexOf(d.unit) < 0, max: d.unit === 'percent' ? 100 : undefined, fontColor: '#94a3b8', maxTicksLimit: compact ? 4 : 6, callback: function (v) { return fmt(v, d.unit); } },
                        gridLines: { color: '#eef2f7', drawBorder: false },
                    }],
                    xAxes: [{ ticks: Object.assign(xTicks, { fontColor: '#64748b' }), gridLines: { display: false } }],
                },
            },
        });
        if (el.hasAttribute('data-zoom')) zoomable(el);
    }

    /** Drag across the plot to pick a time range. */
    function zoomable(el) {
        if (el._zoomBound) return;
        el._zoomBound = true;
        var plot = el.querySelector('.tg-plot'), canvas = plot.querySelector('canvas');
        var band = document.createElement('div');
        band.className = 'tg-zoom';
        plot.appendChild(band);
        var startX = null;
        function area() { return el._chart ? el._chart.chartArea : null; }
        function indexAt(x) {
            var a = area(), n = el._data.times.length;
            return Math.max(0, Math.min(n - 1, Math.round((x - a.left) / (a.right - a.left) * (n - 1))));
        }
        canvas.addEventListener('mousedown', function (e) {
            var a = area(); if (!a) return;
            var x = e.offsetX; if (x < a.left || x > a.right) return;
            startX = x; band.style.display = 'block'; band.style.left = x + 'px'; band.style.width = '0px';
            band.style.top = a.top + 'px'; band.style.height = (a.bottom - a.top) + 'px';
        });
        canvas.addEventListener('mousemove', function (e) {
            if (startX === null) return;
            var a = area(), x = Math.max(a.left, Math.min(a.right, e.offsetX));
            band.style.left = Math.min(startX, x) + 'px'; band.style.width = Math.abs(x - startX) + 'px';
        });
        window.addEventListener('mouseup', function (e) {
            if (startX === null) return;
            var rect = canvas.getBoundingClientRect(), x = e.clientX - rect.left, from = startX;
            startX = null; band.style.display = 'none';
            if (Math.abs(x - from) < 8) return;
            var times = el._data.times, i1 = indexAt(Math.min(from, x)), i2 = indexAt(Math.max(from, x));
            var f = times[i1], to = times[i2] + el._data.step;
            if (typeof el.onZoom === 'function') el.onZoom(f, to);
            else if (el.getAttribute('data-page-url')) window.location = el.getAttribute('data-page-url') + '&from=' + f + '&to=' + to;
        });
    }

    /** 1- or 5-minute averages on the periods up to 48 hours; remembered in this browser. */
    var avg = '1';
    try { avg = localStorage.getItem('tg-avg') === '5' ? '5' : '1'; } catch (e) {}

    function setAvg(v) {
        avg = v === '5' ? '5' : '1';
        try { localStorage.setItem('tg-avg', avg); } catch (e) {}
        document.querySelectorAll('.tg').forEach(function (el) { if (el._data) load(el); });
    }

    function url(el, format) {
        var u = el.getAttribute('data-url'), sep = u.indexOf('?') >= 0 ? '&' : '?';
        var range = el.getAttribute('data-from') && el.getAttribute('data-to')
            ? 'from=' + el.getAttribute('data-from') + '&to=' + el.getAttribute('data-to')
            : 'graph=' + (el.getAttribute('data-graph') || 'day');
        return u + sep + range + '&avg=' + avg + (el.hasAttribute('data-points') && !format ? '&points=' + el.getAttribute('data-points') : '') + (format ? '&format=' + format : '');
    }

    function load(el, graph) {
        if (graph) { el.setAttribute('data-graph', graph); el.removeAttribute('data-from'); el.removeAttribute('data-to'); }
        return fetch(url(el), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (d) { el._data = d; head(el, d); draw(el, d); legend(el, d); if (typeof el.onLoaded === 'function') el.onLoaded(d); return d; })
            .catch(function () { var b = el.querySelector('.tg-legend'); if (b) b.innerHTML = '<span class="text-danger">' + t('Could not load the graph.', 'গ্রাফ লোড করা যায়নি।') + '</span>'; });
    }

    /** Load each graph when it scrolls into view (many graphs on one page). */
    function lazy(els, graph) {
        if (!('IntersectionObserver' in window)) { els.forEach(function (el) { load(el, graph); }); return; }
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) { io.unobserve(e.target); load(e.target, graph); } });
        }, { rootMargin: '200px' });
        els.forEach(function (el) { io.observe(el); });
    }

    /** Save the graph as a PNG (white background). */
    function png(el, name) {
        var c = el.querySelector('canvas'), out = document.createElement('canvas');
        out.width = c.width; out.height = c.height;
        var ctx = out.getContext('2d');
        ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, out.width, out.height); ctx.drawImage(c, 0, 0);
        var a = document.createElement('a');
        a.download = (name || 'graph') + '.png'; a.href = out.toDataURL('image/png');
        document.body.appendChild(a); a.click(); a.remove();
    }

    return {
        load: load, lazy: lazy, setAvg: setAvg, getAvg: function () { return avg; }, png: png,
        csvUrl: function (el) { return url(el, 'csv'); }, fmt: fmt,
        bps: function (v) { return fmt(v, 'bps'); }, bytes: function (v) { return total(v, 'bytes'); },
    };
})();
