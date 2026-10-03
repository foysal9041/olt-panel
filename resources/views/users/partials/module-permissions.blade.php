{{--
    Module access as a grid of switches: a module's "Full access" switch, or
    single sections of it. "Use role defaults" fills in the picked role's
    starting set (config/roles.php); a new user gets it automatically when
    the role changes. Admins have everything, so the grid is dimmed for them.
--}}
@php
    $permissionState = old('permissions', $permissionState ?? []);
    $rolePresets = collect(config('roles'))->map(fn ($r) => $r['permissions'])->all();
    $isNewUser = ! ($user->exists ?? false);
@endphp

<div class="perm" id="perm">
    <div class="perm-head">
        <div>
            <div class="perm-title">Module access</div>
            <div class="perm-sub">Turn on a whole module, or only the sections this person needs. Dashboard and Profile are always open.</div>
        </div>
        <div class="perm-tools">
            <button type="button" class="btn btn-sm btn-primary js-perm-role"><i class="fas fa-magic"></i> Use <span class="js-perm-role-name">role</span> defaults</button>
            <button type="button" class="btn btn-sm btn-light border js-perm-all">All</button>
            <button type="button" class="btn btn-sm btn-light border js-perm-none">None</button>
        </div>
    </div>

    <div class="perm-admin-note"><i class="fas fa-crown"></i> Admins can open everything — these switches don't apply to them.</div>

    <div class="perm-grid">
        @foreach ($modules as $key => $module)
            @php
                $state = $permissionState[$key] ?? [];
                $isFull = ! empty($state['full']);
                $subSelected = $state['sub'] ?? [];
                $subs = $module['submodules'] ?? [];
            @endphp
            <div class="perm-card" data-module="{{ $key }}">
                <div class="perm-card-head">
                    <span class="perm-ic"><i class="{{ $module['icon'] }}"></i></span>
                    <div class="perm-name">
                        <b>{{ \App\Support\Ui::t($module['label']) }}</b>
                        <small><span class="js-perm-count">0</span> / {{ count($subs) }} sections</small>
                    </div>
                    <div class="custom-control custom-switch" title="Full access — every section, including ones added later">
                        <input type="checkbox" class="custom-control-input js-full" id="perm_{{ $key }}_full" name="permissions[{{ $key }}][full]" value="1" @checked($isFull)>
                        <label class="custom-control-label small font-weight-bold" for="perm_{{ $key }}_full">Full</label>
                    </div>
                </div>
                @if ($subs)
                    <div class="perm-subs">
                        @foreach ($subs as $subKey => $subLabel)
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input js-sub" id="perm_{{ $key }}_{{ $subKey }}" name="permissions[{{ $key }}][sub][]" value="{{ $subKey }}"
                                       @checked($isFull || in_array($subKey, $subSelected))>
                                <label class="custom-control-label" for="perm_{{ $key }}_{{ $subKey }}">{{ \App\Support\Ui::t($subLabel) }}</label>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>

<style>
    .perm-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: .9rem; }
    .perm-title { font-weight: 700; color: #0f172a; }
    .perm-sub { font-size: .82rem; color: #64748b; }
    .perm-tools { display: flex; gap: .4rem; flex-wrap: wrap; }
    .perm-admin-note { display: none; margin-bottom: .8rem; padding: .55rem .8rem; border-radius: .6rem; background: #fff1f2; color: #be123c; font-size: .85rem; font-weight: 600; }
    .perm.is-admin .perm-admin-note { display: block; }
    .perm.is-admin .perm-grid, .perm.is-admin .perm-tools { opacity: .45; pointer-events: none; }
    .perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: .9rem; }
    .perm-card { border: 1px solid #e2e8f0; border-radius: .9rem; background: #fff; transition: border-color .15s, box-shadow .15s; }
    .perm-card.is-on { border-color: #a5b4fc; box-shadow: 0 0 0 3px rgba(99, 102, 241, .08); }
    .perm-card-head { display: flex; align-items: center; gap: .65rem; padding: .75rem .9rem; border-bottom: 1px solid #f1f5f9; }
    .perm-ic { flex: none; display: grid; place-items: center; width: 2.2rem; height: 2.2rem; border-radius: .65rem; background: #eef2ff; color: #4f46e5; }
    .perm-card.is-full .perm-ic { background: #4f46e5; color: #fff; }
    .perm-name { flex: 1; min-width: 0; line-height: 1.2; }
    .perm-name b { display: block; color: #0f172a; }
    .perm-name small { color: #94a3b8; font-weight: 600; }
    .perm-subs { padding: .6rem .9rem .75rem; display: grid; gap: .35rem; }
    .perm-subs .custom-control-label { font-size: .86rem; color: #334155; }
    .perm-card.is-full .perm-subs { opacity: .55; }
</style>

<script>
(function () {
    var root = document.getElementById('perm');
    if (!root) return;
    var presets = @json($rolePresets);
    var roleNames = @json(collect(config('roles'))->map(fn ($r) => \App\Support\Ui::t($r['label'])));
    var isNew = @json($isNewUser);
    var roleInputs = document.querySelectorAll('input[name="role"]');

    function role() { var r = document.querySelector('input[name="role"]:checked'); return r ? r.value : null; }

    function refresh() {
        root.querySelectorAll('.perm-card').forEach(function (card) {
            var full = card.querySelector('.js-full').checked, subs = card.querySelectorAll('.js-sub'), on = 0;
            subs.forEach(function (s) { if (full) s.checked = true; s.disabled = full; if (s.checked) on++; });
            card.querySelector('.js-perm-count').textContent = on;
            card.classList.toggle('is-full', full);
            card.classList.toggle('is-on', full || on > 0);
        });
        root.classList.toggle('is-admin', role() === 'admin');
        root.querySelector('.js-perm-role-name').textContent = roleNames[role()] || 'role';
    }

    function apply(preset) {
        root.querySelectorAll('.perm-card').forEach(function (card) {
            var mod = card.dataset.module, want = preset === '*' ? '*' : (preset && preset[mod]);
            card.querySelector('.js-full').checked = want === '*';
            card.querySelectorAll('.js-sub').forEach(function (s) { s.checked = Array.isArray(want) && want.indexOf(s.value) !== -1; });
        });
        refresh();
    }

    root.addEventListener('change', refresh);
    root.querySelector('.js-perm-role').addEventListener('click', function () { apply(presets[role()] || {}); });
    root.querySelector('.js-perm-all').addEventListener('click', function () { apply('*'); });
    root.querySelector('.js-perm-none').addEventListener('click', function () { apply({}); });
    roleInputs.forEach(function (r) {
        r.addEventListener('change', function () { if (isNew) apply(presets[role()] || {}); else refresh(); });
    });

    // Disabled boxes aren't sent: re-enable them just before the form goes.
    root.closest('form').addEventListener('submit', function () { root.querySelectorAll('.js-sub').forEach(function (s) { s.disabled = false; }); });

    if (isNew && !root.querySelector('.js-full:checked, .js-sub:checked')) apply(presets[role()] || {});
    refresh();
})();
</script>
