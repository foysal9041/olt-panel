/**
 * LiveRefresh — keep a page's numbers fresh without reloading it.
 *
 * Every few seconds the page's own URL is fetched in the background and
 * only the marked parts are updated, so scroll position, filters, open
 * dropdowns/lists and table paging all stay as they are:
 *
 *   data-live="name"        this element's content (and class) is replaced
 *                           when the fresh page has something different;
 *   data-live-keep="name"   inside a live element: kept as-is (charts and
 *                           graphs that refresh themselves);
 *   data-live-table="name"  a table whose rows carry data-key: rows are
 *                           updated / added / removed in place (works with
 *                           DataTables — search, sort and page are kept);
 *   data-live-ignore        a cell never compared (e.g. SL numbers).
 *
 * After an update a `live:updated` event fires on document with
 * detail.keys = the names that changed, so a page can re-bind its widgets.
 *
 * Start it from a page:  LiveRefresh.start(30000);
 */
(function () {
    'use strict';

    var state = { interval: 30000, timer: null, busy: false, lastOk: null, failed: false, pill: null };

    function el(tag, cls, html) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (html) e.innerHTML = html;
        return e;
    }

    // ---- Status pill ---------------------------------------------------------

    function ago(ts) {
        var s = Math.round((Date.now() - ts) / 1000);
        if (s < 5) return 'just now';
        if (s < 60) return s + 's ago';
        return Math.round(s / 60) + 'm ago';
    }

    function paint() {
        if (!state.pill) return;
        var text = state.pill.querySelector('.live-text');
        state.pill.classList.toggle('is-off', state.failed);
        state.pill.classList.toggle('is-busy', state.busy);
        text.textContent = state.failed
            ? 'Reconnecting…'
            : 'Live · updated ' + (state.lastOk ? ago(state.lastOk) : 'just now');
    }

    function mountPill() {
        state.pill = el('button', 'live-pill', '<span class="live-dot"></span><span class="live-text"></span>');
        state.pill.type = 'button';
        state.pill.title = 'Data updates by itself — click to update now';
        state.pill.addEventListener('click', function () { refresh(true); });
        document.body.appendChild(state.pill);
        setInterval(paint, 5000);
        paint();
    }

    // ---- Updating ---------------------------------------------------------------

    function flash(node) {
        if (!node || !node.classList) return;
        node.classList.remove('live-flash');
        void node.offsetWidth; // restart the animation
        node.classList.add('live-flash');
        setTimeout(function () { node.classList.remove('live-flash'); }, 1600);
    }

    // Someone is using this part of the page right now: leave it alone.
    function inUse(node) {
        var a = document.activeElement;
        if (a && a !== document.body && node.contains(a) && /^(INPUT|SELECT|TEXTAREA|BUTTON)$/.test(a.tagName)) return true;
        return !!node.querySelector('.select2-container--open');
    }

    function openDetails(node) {
        var keys = [];
        node.querySelectorAll('details[data-key][open]').forEach(function (d) { keys.push(d.dataset.key); });
        return keys;
    }

    // What the server sent for a region (kept pieces blanked out). Compared
    // with the last server copy — not the live DOM, which page scripts may
    // have changed (filters hiding tiles, numbering …).
    var SYNCED_ATTRS = ['class', 'style', 'title', 'href'];

    function snapshot(node) {
        var copy = node.cloneNode(true);
        copy.querySelectorAll('[data-live-keep]').forEach(function (k) { k.innerHTML = ''; });
        return SYNCED_ATTRS.map(function (a) { return node.getAttribute(a) || ''; }).join('\u0001') + '\u0000' + copy.innerHTML;
    }

    function updateRegion(cur, fresh) {
        var snap = snapshot(fresh);
        if (cur.__liveSnap === snap) return false;

        // Keep self-updating pieces (graphs) and put them back afterwards.
        var kept = {};
        cur.querySelectorAll('[data-live-keep]').forEach(function (k) { kept[k.dataset.liveKeep] = k; });
        var open = openDetails(cur);

        SYNCED_ATTRS.forEach(function (a) {
            if (fresh.hasAttribute(a)) cur.setAttribute(a, fresh.getAttribute(a)); else cur.removeAttribute(a);
        });
        cur.innerHTML = fresh.innerHTML;
        cur.querySelectorAll('[data-live-keep]').forEach(function (slot) {
            var old = kept[slot.dataset.liveKeep];
            if (old) slot.parentNode.replaceChild(old, slot);
        });
        cur.querySelectorAll('details[data-key]').forEach(function (d) {
            if (open.indexOf(d.dataset.key) !== -1) d.open = true;
        });

        cur.__liveSnap = snap;
        flash(cur);
        return true;
    }

    // A row's own attributes that page scripts read (e.g. data-state for a
    // port filter) — everything but data-key, class handled separately.
    function rowAttrs(row) {
        var out = {};
        Array.prototype.forEach.call(row.attributes, function (a) {
            if (a.name.indexOf('data-') === 0 && a.name !== 'data-key') out[a.name] = a.value;
        });
        return out;
    }

    // Classes added in the browser (DataTables striping/sorting, our flash)
    // aren't data: ignore them when comparing, keep them when updating.
    var LOCAL_CLASS = /^(odd|even|sorting_\d+|live-flash|dtr-.*|selected)$/;

    function splitClass(node) {
        var own = [], local = [];
        (node.getAttribute('class') || '').split(/\s+/).filter(Boolean).forEach(function (c) {
            (LOCAL_CLASS.test(c) ? local : own).push(c);
        });
        return { own: own.join(' '), local: local };
    }

    function setClass(cur, fresh) {
        var keep = splitClass(cur).local;
        var cls = splitClass(fresh).own.split(' ').concat(keep).filter(Boolean).join(' ');
        if (cls) cur.setAttribute('class', cls); else cur.removeAttribute('class');
    }

    var CELL_ATTRS = ['title', 'data-order'];

    function cellSig(c) {
        return splitClass(c).own + '\u0001' + CELL_ATTRS.map(function (a) { return c.getAttribute(a) || ''; }).join('\u0001') + '\u0000' + c.innerHTML;
    }

    function cellsDiffer(a, b) {
        if (splitClass(a).own !== splitClass(b).own) return true;
        if (JSON.stringify(rowAttrs(a)) !== JSON.stringify(rowAttrs(b))) return true;
        for (var i = 0; i < b.cells.length; i++) {
            if (!a.cells[i] || a.cells[i].hasAttribute('data-live-ignore')) continue;
            if (cellSig(a.cells[i]) !== cellSig(b.cells[i])) return true;
        }
        return false;
    }

    function copyRow(cur, fresh) {
        setClass(cur, fresh);
        var attrs = rowAttrs(fresh);
        Object.keys(rowAttrs(cur)).forEach(function (n) { if (!(n in attrs)) cur.removeAttribute(n); });
        Object.keys(attrs).forEach(function (n) { cur.setAttribute(n, attrs[n]); });

        for (var i = 0; i < fresh.cells.length; i++) {
            var c = cur.cells[i], f = fresh.cells[i];
            if (!c || c.hasAttribute('data-live-ignore') || cellSig(c) === cellSig(f)) continue;
            setClass(c, f);
            CELL_ATTRS.forEach(function (a) {
                if (f.hasAttribute(a)) c.setAttribute(a, f.getAttribute(a)); else c.removeAttribute(a);
            });
            c.innerHTML = f.innerHTML;
            flash(c);
        }
    }

    function updateTable(cur, fresh) {
        var $ = window.jQuery;
        var freshRows = {};
        fresh.querySelectorAll('tbody > tr[data-key]').forEach(function (r) { freshRows[r.dataset.key] = r; });

        var dt = $ && $.fn.dataTable && $.fn.dataTable.isDataTable(cur) ? $(cur).DataTable() : null;
        var rows = dt ? dt.rows().nodes().toArray() : Array.prototype.slice.call(cur.querySelectorAll('tbody > tr[data-key]'));
        var seen = {}, gone = [], changed = false;

        rows.forEach(function (row) {
            var f = freshRows[row.dataset.key];
            if (!f) { gone.push(row); return; }
            seen[row.dataset.key] = true;
            if (cellsDiffer(row, f)) { copyRow(row, f); changed = true; }
        });

        var added = Object.keys(freshRows).filter(function (k) { return !seen[k]; });

        if (dt) {
            gone.forEach(function (row) { dt.row(row).remove(); });
            added.forEach(function (k) { var n = freshRows[k].cloneNode(true); dt.row.add(n); flash(n); });
            if (changed || gone.length || added.length) dt.rows().invalidate('dom').draw(false);
        } else {
            var body = cur.tBodies[0];
            gone.forEach(function (row) { row.remove(); });
            added.forEach(function (k) { var n = freshRows[k].cloneNode(true); body.appendChild(n); flash(n); });
        }

        return changed || gone.length > 0 || added.length > 0;
    }

    function apply(doc) {
        var keys = [];

        document.querySelectorAll('[data-live]').forEach(function (cur) {
            var fresh = doc.querySelector('[data-live="' + cur.dataset.live + '"]');
            if (!fresh || inUse(cur)) return;
            if (updateRegion(cur, fresh)) keys.push(cur.dataset.live);
        });

        document.querySelectorAll('table[data-live-table]').forEach(function (cur) {
            var fresh = doc.querySelector('table[data-live-table="' + cur.dataset.liveTable + '"]');
            if (!fresh) return;
            if (updateTable(cur, fresh)) keys.push(cur.dataset.liveTable);
        });

        if (keys.length) {
            document.dispatchEvent(new CustomEvent('live:updated', { detail: { keys: keys } }));
        }
    }

    function refresh(now) {
        if (state.busy || (!now && document.hidden)) return;
        state.busy = true;
        paint();

        fetch(location.href, { credentials: 'same-origin', headers: { 'X-Live-Refresh': '1', 'Accept': 'text/html' } })
            .then(function (res) {
                // Signed out meanwhile: show the login page properly.
                if (res.redirected && /\/login(\b|$)/.test(res.url)) { location.reload(); throw new Error('signed out'); }
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.text();
            })
            .then(function (html) {
                apply(new DOMParser().parseFromString(html, 'text/html'));
                state.failed = false;
                state.lastOk = Date.now();
            })
            .catch(function () { state.failed = true; })
            .finally(function () { state.busy = false; paint(); });
    }

    function schedule() {
        clearInterval(state.timer);
        state.timer = setInterval(refresh, state.interval);
    }

    window.LiveRefresh = {
        // Call from the page's scripts (before its widgets change the DOM),
        // so the snapshot is exactly what the server sent.
        start: function (ms) {
            state.interval = Math.max(5000, ms || 30000);
            state.lastOk = Date.now();
            document.querySelectorAll('[data-live]').forEach(function (n) { n.__liveSnap = snapshot(n); });
            var go = function () {
                mountPill();
                schedule();
                // Back on the tab after a while: update straight away.
                document.addEventListener('visibilitychange', function () {
                    if (!document.hidden && Date.now() - (state.lastOk || 0) > state.interval) refresh(true);
                });
            };
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
        },
        refresh: function () { refresh(true); },
    };
})();
