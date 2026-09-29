{{--
    Which OLTs and switches this user may see: tick the devices, that's it.
    "All" also covers devices added later. Admins always see everything.
    Expects $user (may be new), $olts, $switches.
--}}
@php
    $groups = [
        'olt' => ['OLT', 'fas fa-network-wired', 'olt_access', 'allowed_olts', $olts, 'allowedOlts'],
        'switch' => ['Switch', 'fas fa-server', 'switch_access', 'allowed_switches', $switches, 'allowedSwitches'],
    ];
@endphp

<div class="form-group">
    <label>Device Access</label>
    <div class="card card-body bg-light device-access">

        <p class="small text-muted mb-3">
            <i class="fas fa-info-circle"></i>
            Tick the OLTs and switches this user can see — <strong>only ticked devices are shown to them</strong>.
            Admins always see every device.
        </p>

        <div class="row">
            @foreach ($groups as $type => [$label, $icon, $field, $listField, $devices, $relation])
                @php
                    $mode = $user->exists ? $user->deviceAccess($type) : 'selected';

                    // Pre-tick what the user can see today (for older "zone"
                    // users that's the devices in their zone).
                    $current = match (true) {
                        ! $user->exists => [],
                        $mode === 'zone' => $devices->where('zone', $user->zone)->pluck('id')->all(),
                        default => $user->{$relation}->pluck('id')->all(),
                    };

                    $isAll = old($field) !== null ? old($field) === 'all' : $mode === 'all';
                    $picked = array_map('intval', old($listField, $current));
                @endphp
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <div class="da-box" data-type="{{ $type }}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="da-head"><i class="{{ $icon }}"></i> {{ $label }}s</div>
                            <span class="small text-muted"><span class="js-da-count">0</span> / {{ $devices->count() }} selected</span>
                        </div>

                        {{-- "all" or "selected"; the checkbox below flips it --}}
                        <input type="hidden" name="{{ $field }}" value="{{ $isAll ? 'all' : 'selected' }}" class="js-da-mode">

                        <div class="custom-control custom-checkbox da-all mb-2">
                            <input type="checkbox" class="custom-control-input js-da-all-toggle" id="{{ $field }}_all" @checked($isAll)>
                            <label class="custom-control-label font-weight-bold" for="{{ $field }}_all">
                                All {{ $label }}s <small class="text-muted font-weight-normal">— including ones added later</small>
                            </label>
                        </div>

                        @if ($devices->isEmpty())
                            <div class="small text-muted">No {{ strtolower($label) }}s added yet.</div>
                        @else
                            <div class="d-flex align-items-center mb-2" style="gap:.5rem">
                                <input type="search" class="form-control form-control-sm js-da-search" placeholder="Search {{ strtolower($label) }}s…">
                                <button type="button" class="btn btn-sm btn-light text-nowrap js-da-all">Select all</button>
                                <button type="button" class="btn btn-sm btn-light text-nowrap js-da-none">None</button>
                            </div>
                            <div class="da-items">
                                @foreach ($devices as $d)
                                    <div class="custom-control custom-checkbox da-item" data-search="{{ strtolower($d->name . ' ' . $d->ip . ' ' . $d->zone) }}">
                                        <input type="checkbox" class="custom-control-input" id="{{ $listField }}_{{ $d->id }}"
                                               name="{{ $listField }}[]" value="{{ $d->id }}" @checked($isAll || in_array($d->id, $picked, true))>
                                        <label class="custom-control-label" for="{{ $listField }}_{{ $d->id }}">
                                            {{ $d->name }}
                                            <small class="text-muted">{{ $d->ip }}{{ $d->zone ? ' · ' . $d->zone : '' }}</small>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<style>
    .device-access .da-box { height: 100%; padding: .85rem 1rem; border-radius: .6rem; background: #fff; border: 1px solid #e2e8f0; }
    .device-access .da-head { font-weight: 700; color: #0f172a; }
    .device-access .da-head i { color: #4f46e5; margin-right: .3rem; }
    .device-access .da-all { padding: .45rem .6rem .45rem 2.1rem; border-radius: .45rem; background: #eef2ff; }
    .device-access .da-items { max-height: 240px; overflow-y: auto; padding: .4rem .6rem; border: 1px solid #eef2f7; border-radius: .45rem; }
    .device-access .da-items.locked { opacity: .55; pointer-events: none; }
    .device-access .da-item { padding: .15rem 0; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.device-access .da-box').forEach(function (box) {
        var mode = box.querySelector('.js-da-mode');
        var allToggle = box.querySelector('.js-da-all-toggle');
        var items = box.querySelector('.da-items');
        var boxes = box.querySelectorAll('.da-item input');
        var count = box.querySelector('.js-da-count');
        var search = box.querySelector('.js-da-search');

        function sync() {
            var all = allToggle.checked;
            mode.value = all ? 'all' : 'selected';
            if (items) items.classList.toggle('locked', all);
            if (all) boxes.forEach(function (b) { b.checked = true; });
            count.textContent = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).length;
        }

        allToggle.addEventListener('change', function () {
            // Leaving "All": start from nothing ticked so the choice is deliberate.
            if (!allToggle.checked) boxes.forEach(function (b) { b.checked = false; });
            sync();
        });

        boxes.forEach(function (b) { b.addEventListener('change', sync); });

        if (search) search.addEventListener('input', function () {
            var q = search.value.trim().toLowerCase();
            box.querySelectorAll('.da-item').forEach(function (item) {
                item.hidden = q && item.dataset.search.indexOf(q) === -1;
            });
        });

        var selectAll = box.querySelector('.js-da-all');
        if (selectAll) selectAll.addEventListener('click', function () {
            box.querySelectorAll('.da-item:not([hidden]) input').forEach(function (b) { b.checked = true; });
            sync();
        });

        var none = box.querySelector('.js-da-none');
        if (none) none.addEventListener('click', function () {
            box.querySelectorAll('.da-item:not([hidden]) input').forEach(function (b) { b.checked = false; });
            sync();
        });

        sync();
    });
});
</script>
