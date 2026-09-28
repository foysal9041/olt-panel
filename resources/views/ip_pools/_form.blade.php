{{--
    Allocate / edit a subnet. Expects $ipPool, $blocks, $zones, $devices, $purposes, $action, $method.
--}}
@php
    $err = fn ($f) => $errors->has($f) ? ' is-invalid' : '';
    $val = fn ($f) => old($f, $ipPool->{$f});
    $selectedBlock = $blocks->firstWhere('id', (int) $val('ip_block_id'));
@endphp

<form method="POST" action="{{ $action }}" id="ip-form">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card acct-panel">
                <div class="card-body">

                    <div class="row">
                        <div class="col-md-5 form-group">
                            <label for="ip_block_id">Block</label>
                            <select id="ip_block_id" name="ip_block_id" class="form-control">
                                <option value="" data-cidr="">— None (standalone) —</option>
                                @foreach ($blocks as $b)
                                    <option value="{{ $b->id }}" data-cidr="{{ $b->cidr }}" data-type="{{ $b->type }}" @selected((string) $val('ip_block_id') === (string) $b->id)>
                                        {{ $b->cidr }} — {{ $b->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-7 form-group">
                            <label for="subnet">Subnet <span class="text-danger">*</span></label>
                            <input type="text" id="subnet" name="subnet" class="form-control mono{{ $err('subnet') }}"
                                   value="{{ $val('subnet') }}" placeholder="103.161.2.48/30" required autofocus autocomplete="off">
                            @error('subnet') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div id="subnet-calc" class="subnet-calc" hidden></div>
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="device">Device</label>
                            <input type="text" id="device" name="device" class="form-control{{ $err('device') }}" list="ip-devices"
                                   value="{{ $val('device') }}" placeholder="e.g. NAT-1, DIS-4">
                            <datalist id="ip-devices">@foreach ($devices as $d)<option value="{{ $d }}">@endforeach</datalist>
                            @error('device') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="purpose">Purpose</label>
                            <input type="text" id="purpose" name="purpose" class="form-control{{ $err('purpose') }}" list="ip-purposes"
                                   value="{{ $val('purpose') }}" placeholder="e.g. Natting, P2P, PPPoE">
                            <datalist id="ip-purposes">
                                @foreach ($purposes->merge(['Natting', 'P2P', 'PPPoE', 'Server and VM', 'VPN'])->unique()->sort() as $p)<option value="{{ $p }}">@endforeach
                            </datalist>
                            @error('purpose') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="private_subnet">Private IP (PPPoE pool)</label>
                            <input type="text" id="private_subnet" name="private_subnet" class="form-control mono{{ $err('private_subnet') }}"
                                   value="{{ $val('private_subnet') }}" placeholder="10.170.0.0/16">
                            @error('private_subnet') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="vlan">Pairing VLAN</label>
                            <input type="text" id="vlan" name="vlan" class="form-control mono{{ $err('vlan') }}"
                                   value="{{ $val('vlan') }}" placeholder="210-213, 2435-2439">
                            @error('vlan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="gateway">Gateway</label>
                            <input type="text" id="gateway" name="gateway" class="form-control mono{{ $err('gateway') }}"
                                   value="{{ $val('gateway') }}" placeholder="optional">
                            @error('gateway') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="zone">Zone</label>
                            <select id="zone" name="zone" class="form-control">
                                <option value="">— None —</option>
                                @foreach ($zones as $z)
                                    <option value="{{ $z }}" @selected($val('zone') == $z)>{{ $z }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 form-group" id="type-group" @if ($selectedBlock) hidden @endif>
                            <label for="type">Type</label>
                            <select id="type" name="type" class="form-control">
                                <option value="public" @selected($val('type') === 'public')>Public</option>
                                <option value="private" @selected($val('type') === 'private')>Private</option>
                            </select>
                        </div>

                        <div class="col-12 form-group mb-0">
                            <label for="description">Notes</label>
                            <textarea id="description" name="description" rows="2" class="form-control" placeholder="Optional">{{ $val('description') }}</textarea>
                        </div>
                    </div>

                    <input type="hidden" name="status" value="{{ $val('status') ?: 'active' }}">
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card acct-panel">
                <div class="card-body small text-muted">
                    <p class="mb-2"><i class="fas fa-magic text-primary"></i> Type a subnet and the network, broadcast and usable range are worked out for you.</p>
                    <p class="mb-2"><i class="fas fa-crosshairs text-primary"></i> If you type an address inside a subnet (e.g. <code>.50/30</code>), it's saved as the real network (<code>.48/30</code>).</p>
                    <p class="mb-0"><i class="fas fa-shield-alt text-primary"></i> A subnet must sit inside its block and can't overlap another subnet or an NTTN link's subnet.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-3" style="gap:.5rem">
        <a href="{{ $selectedBlock ? route('ip-blocks.show', $selectedBlock) : route('ip-pools.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ $ipPool->exists ? 'Save Changes' : 'Allocate' }}</button>
    </div>
</form>

@section('css')
<style>
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .subnet-calc { margin-top: .5rem; padding: .6rem .75rem; border-radius: .55rem; background: #f8fafc; border: 1px solid #e2e8f0; font-size: .8rem; }
    .subnet-calc.warn { background: #fffbeb; border-color: #fde68a; }
    .subnet-calc.bad { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }
    .subnet-calc dl { display: grid; grid-template-columns: auto 1fr; gap: .15rem .75rem; margin: 0; }
    .subnet-calc dt { font-weight: 500; color: #64748b; }
    .subnet-calc dd { margin: 0; font-family: ui-monospace, monospace; color: #0f172a; }
</style>
@stop

@section('js')
<script>
(function () {
    var input = document.getElementById('subnet');
    var box = document.getElementById('subnet-calc');
    var blockSel = document.getElementById('ip_block_id');
    var typeGroup = document.getElementById('type-group');

    function toInt(ip) {
        var p = ip.split('.');
        if (p.length !== 4 || p.some(function (x) { return !/^\d{1,3}$/.test(x) || +x > 255; })) return null;
        return ((+p[0] << 24) >>> 0) + (+p[1] << 16) + (+p[2] << 8) + (+p[3]);
    }
    function toIp(n) { return [n >>> 24, (n >>> 16) & 255, (n >>> 8) & 255, n & 255].join('.'); }
    function parse(cidr) {
        var m = String(cidr).trim().match(/^([\d.]+)\/(\d{1,2})$/);
        if (!m || +m[2] > 32) return null;
        var ip = toInt(m[1]); if (ip === null) return null;
        var prefix = +m[2];
        var size = Math.pow(2, 32 - prefix);
        var net = Math.floor(ip / size) * size;
        return { ip: ip, prefix: prefix, net: net, bc: net + size - 1, size: size };
    }

    function render() {
        var v = input.value.trim();
        if (!v) { box.hidden = true; return; }
        var s = parse(v);
        box.hidden = false;

        if (!s) {
            box.className = 'subnet-calc bad';
            box.innerHTML = '<i class="fas fa-times-circle"></i> Use CIDR form, e.g. 103.161.2.48/30';
            return;
        }

        var first = s.prefix >= 31 ? s.net : s.net + 1;
        var last = s.prefix >= 31 ? s.bc : s.bc - 1;
        var warn = '';
        var cls = 'subnet-calc';

        if (s.ip !== s.net) {
            cls += ' warn';
            warn = '<div class="mb-1"><i class="fas fa-exclamation-triangle text-warning"></i> Not the network address — will be saved as <strong class="mono">' +
                toIp(s.net) + '/' + s.prefix + '</strong> ' +
                '<a href="#" id="fix-subnet">use it</a></div>';
        }

        var opt = blockSel.options[blockSel.selectedIndex];
        var b = opt && opt.dataset.cidr ? parse(opt.dataset.cidr) : null;
        if (b && (s.net < b.net || s.bc > b.bc)) {
            cls = 'subnet-calc bad';
            warn += '<div class="mb-1"><i class="fas fa-times-circle"></i> Outside the block ' + opt.dataset.cidr + '</div>';
        }

        box.className = cls;
        box.innerHTML = warn +
            '<dl>' +
                '<dt>Network</dt><dd>' + toIp(s.net) + '</dd>' +
                '<dt>Broadcast</dt><dd>' + toIp(s.bc) + '</dd>' +
                '<dt>Usable</dt><dd>' + toIp(first) + ' – ' + toIp(last) + ' (' + (last - first + 1) + ')</dd>' +
                '<dt>Size</dt><dd>/' + s.prefix + ' · ' + s.size + ' addresses</dd>' +
            '</dl>';

        var fix = document.getElementById('fix-subnet');
        if (fix) fix.addEventListener('click', function (e) {
            e.preventDefault();
            input.value = toIp(s.net) + '/' + s.prefix;
            render();
        });
    }

    input.addEventListener('input', render);
    blockSel.addEventListener('change', function () {
        typeGroup.hidden = !!blockSel.value;
        render();
    });
    render();
})();
</script>
@stop
