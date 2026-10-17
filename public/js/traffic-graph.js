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
    var STEP_LABEL = { 60: t('1 minute average', '১ মিনিটের গড়'), 300: t('5 minute average', '৫ মিনিটের গড়'), 1800: t('30 minute average', '৩০ মিনিটের গড়'), 7200: t('2 hour average', '২ ঘণ্টার গড়'), 86400: t('1 day average', '১ দিনের গড়') };

    function tr(name) { return t(name, NAMES[name] || name); }
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

    function total(v, unit) {
        if (v === null || v === undefined) return '—';
        return unit === 'bytes' ? scaled(v, ['B', 'KB', 'MB', 'GB', 'TB', 'PB'], 1000) : scaled(v, ['', 'K', 'M', 'G'], 1000).trim();
    }

    function clock(d) {
        var h = d.getHours();
        return pad(h % 12 || 12) + ':' + pad(d.getMinutes()) + (h >= 12 ? ' PM' : ' AM');
    }

    /** Axis label, by how long the graph spans. */
    function tick(ts, span) {
        var d = new Date(ts * 1000);
        if (span <= 86400 + 60) return clock(d);
        if (span <= 8 * 86400) return DAYS[d.getDay()] + ' ' + pad(d.getHours()) + ':00';
        if (span <= 62 * 86400) return d.getDate() + ' ' + MONTHS[d.getMonth()];
        return MONTHS[d.getMonth()] + ' ' + d.getFullYear();
    }

    function full(ts, step) {
        var d = new Date(ts * 1000);
        var date = d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear();
        return step >= 86400 ? date : date + ', ' + clock(d);
    }

    function head(el, d) {
        var box = el.querySelector('.tg-head');
        if (!box || el.hasAttribute('data-compact')) { if (box) box.innerHTML = ''; return; }
        box.innerHTML = '<span class="tg-title">' + (el.getAttribute('data-title') || tr(d.title)) + ' · ' + t(d.period, TITLES[d.period] || d.period) + '</span>' +
            '<span class="tg-sub">' + (STEP_LABEL[d.step] || '') + ' · ' + full(d.start, d.step) + ' – ' + full(d.end - d.step, d.step) + '</span>';
    }

    function legend(el, d) {
        var box = el.querySelector('.tg-legend');
        if (!box) return;
        if (!d.series.length) { box.innerHTML = ''; return; }
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
        var datasets = d.series.map(function (s, i) {
            return {
                label: tr(s.name), data: s.values, borderColor: s.color,
                backgroundColor: s.fill ? hexA(s.color, 0.3) : s.color,
                borderWidth: s.fill ? 1 : 1.5, pointRadius: 0, pointHoverRadius: 3, lineTension: 0,
                fill: s.fill && i === 0 ? 'origin' : false, spanGaps: false,
            };
        });
        if (d.p95 && !compact) {
            datasets.push({ label: t('95th percentile', '৯৫তম পার্সেন্টাইল') + ' ' + fmt(d.p95, d.unit), data: d.times.map(function () { return d.p95; }), borderColor: P95, borderDash: [6, 4], borderWidth: 1, pointRadius: 0, pointHoverRadius: 0, fill: false });
        }
        var nSeries = d.series.length;

        if (el._chart) el._chart.destroy();
        el._chart = new Chart(el.querySelector('canvas').getContext('2d'), {
            type: 'line',
            data: { labels: d.times.map(function (ts) { return tick(ts, span); }), datasets: datasets },
            options: {
                maintainAspectRatio: false,
                animation: { duration: 0 },
                legend: { display: !compact, position: 'bottom', labels: { boxWidth: 12, fontColor: '#475569' } },
                tooltips: {
                    mode: 'index', intersect: false,
                    filter: function (item) { return item.datasetIndex < nSeries; },
                    callbacks: {
                        title: function (items) { return items.length ? full(d.times[items[0].index], d.step) : ''; },
                        label: function (item) {
                            var s = d.series[item.datasetIndex], v = s.values[item.index], peak = s.peaks ? s.peaks[item.index] : null;
                            return tr(s.name) + ' ' + fmt(v, d.unit) + (d.step > 60 && peak !== null && peak !== undefined ? '  (' + t('peak', 'সর্বোচ্চ') + ' ' + fmt(peak, d.unit) + ')' : '');
                        },
                    },
                },
                scales: {
                    yAxes: [{
                        ticks: { beginAtZero: ['celsius', 'volts', 'days'].indexOf(d.unit) < 0, max: d.unit === 'percent' ? 100 : undefined, fontColor: '#94a3b8', maxTicksLimit: compact ? 4 : 6, callback: function (v) { return fmt(v, d.unit); } },
                        gridLines: { color: '#eef2f7', drawBorder: false },
                    }],
                    xAxes: [{ ticks: { fontColor: '#64748b', autoSkip: true, maxTicksLimit: compact ? 6 : 12, maxRotation: 0 }, gridLines: { display: false } }],
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
