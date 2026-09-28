{{--
    Shared add/edit form for NTTN links.
    Expects: $nttnLink (new or existing), $zones, $providers, $action, $method, $submitLabel.
--}}
@php
    $telegram = \App\Models\NocAlertSetting::current();
    $telegramOn = $telegram->telegram_enabled && $telegram->telegram_bot_token && $telegram->chatIds() && $telegram->alert_nttn_status;
    $err = fn ($field) => $errors->has($field) ? ' is-invalid' : '';
    $val = fn ($field) => old($field, $nttnLink->{$field});
@endphp

<form method="POST" action="{{ $action }}" id="nttn-form" novalidate>
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    @if ($errors->any())
        <div class="alert alert-danger py-2">
            <i class="fas fa-exclamation-circle"></i>
            Please fix the {{ $errors->count() === 1 ? 'highlighted field' : $errors->count() . ' highlighted fields' }} below.
        </div>
    @endif

    <div class="row">

        {{-- ================= Main ================= --}}
        <div class="col-lg-8">
            <div class="card nttn-card">

                {{-- Link --}}
                <div class="nttn-section">
                    <div class="nttn-section-head">
                        <span class="nttn-section-icon"><i class="fas fa-link"></i></span>
                        <div>
                            <h3>Link</h3>
                            <p>How the NTTN provider identifies this circuit.</p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="link_id">Link ID <span class="req">*</span></label>
                            <input type="text" id="link_id" name="link_id" class="form-control{{ $err('link_id') }}"
                                   value="{{ $val('link_id') }}" placeholder="e.g. NTTN-FH-00123" required autofocus>
                            @error('link_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="provider">Provider</label>
                            <input type="text" id="provider" name="provider" class="form-control{{ $err('provider') }}"
                                   value="{{ $val('provider') }}" list="nttn-providers" placeholder="e.g. Fiber@Home">
                            <datalist id="nttn-providers">
                                @foreach ($providers as $provider)
                                    <option value="{{ $provider }}">
                                @endforeach
                            </datalist>
                            @error('provider') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="bandwidth">Bandwidth <span class="req">*</span></label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-tachometer-alt"></i></span></div>
                                <input type="text" id="bandwidth" name="bandwidth" class="form-control{{ $err('bandwidth') }}"
                                       value="{{ $val('bandwidth') }}" list="nttn-bandwidths" placeholder="e.g. 1 Gbps" required>
                                @error('bandwidth') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <datalist id="nttn-bandwidths">
                                @foreach (['100 Mbps', '500 Mbps', '1 Gbps', '2 Gbps', '5 Gbps', '10 Gbps', '20 Gbps', '40 Gbps', '100 Gbps'] as $bw)
                                    <option value="{{ $bw }}">
                                @endforeach
                            </datalist>
                        </div>

                        <div class="col-md-6 form-group">
                            <label class="d-block">Status</label>
                            @php $status = old('status', $nttnLink->status ?? 'active'); @endphp
                            <div class="btn-group btn-group-toggle nttn-status" data-toggle="buttons">
                                <label class="btn btn-outline-success {{ $status === 'active' ? 'active' : '' }}">
                                    <input type="radio" name="status" value="active" autocomplete="off" @checked($status === 'active')>
                                    <i class="fas fa-check-circle"></i> Active
                                </label>
                                <label class="btn btn-outline-secondary {{ $status === 'inactive' ? 'active' : '' }}">
                                    <input type="radio" name="status" value="inactive" autocomplete="off" @checked($status === 'inactive')>
                                    <i class="fas fa-pause-circle"></i> Inactive
                                </label>
                            </div>
                            @error('status') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                {{-- Location --}}
                <div class="nttn-section">
                    <div class="nttn-section-head">
                        <span class="nttn-section-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-map-marker-alt"></i></span>
                        <div>
                            <h3>Location</h3>
                            <p>Where the link lands. Shown in down/up alerts.</p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="location">Location / POP <span class="req">*</span></label>
                            <input type="text" id="location" name="location" class="form-control{{ $err('location') }}"
                                   value="{{ $val('location') }}" placeholder="e.g. Jashore POP" required>
                            @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="zone">Zone</label>
                            <select id="zone" name="zone" class="form-control select2-zone{{ $err('zone') }}">
                                <option value="">— None —</option>
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone }}" @selected($val('zone') == $zone)>{{ $zone }}</option>
                                @endforeach
                            </select>
                            @error('zone') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-12 form-group">
                            <label for="address">Address <span class="req">*</span></label>
                            <textarea id="address" name="address" rows="2" class="form-control{{ $err('address') }}"
                                      placeholder="POP / entry point address" required>{{ $val('address') }}</textarea>
                            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                {{-- Networking --}}
                <div class="nttn-section">
                    <div class="nttn-section-head">
                        <span class="nttn-section-icon" style="background:#e0f2fe;color:#0284c7"><i class="fas fa-network-wired"></i></span>
                        <div>
                            <h3>Networking</h3>
                            <p>Addressing and peering. Subnets and VLANs are checked for overlaps on save.</p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="public_ip_subnet">Public IP Subnet</label>
                            <input type="text" id="public_ip_subnet" name="public_ip_subnet" class="form-control mono{{ $err('public_ip_subnet') }}"
                                   value="{{ $val('public_ip_subnet') }}" placeholder="103.150.10.0/29">
                            @error('public_ip_subnet') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-6 form-group">
                            <label for="private_ip_subnet">Private IP Subnet</label>
                            <input type="text" id="private_ip_subnet" name="private_ip_subnet" class="form-control mono{{ $err('private_ip_subnet') }}"
                                   value="{{ $val('private_ip_subnet') }}" placeholder="10.10.10.0/30">
                            @error('private_ip_subnet') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="peering_ip">Peering IP</label>
                            <input type="text" id="peering_ip" name="peering_ip" class="form-control mono{{ $err('peering_ip') }}"
                                   value="{{ $val('peering_ip') }}" placeholder="10.10.10.1">
                            @error('peering_ip') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="peering_vlan">Peering VLAN</label>
                            <input type="text" id="peering_vlan" name="peering_vlan" class="form-control mono{{ $err('peering_vlan') }}"
                                   value="{{ $val('peering_vlan') }}" placeholder="300">
                            @error('peering_vlan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="col-md-4 form-group">
                            <label for="asn">ASN</label>
                            <input type="text" id="asn" name="asn" class="form-control mono{{ $err('asn') }}"
                                   value="{{ $val('asn') }}" placeholder="AS137074">
                            @error('asn') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="nttn-section">
                    <div class="nttn-section-head">
                        <span class="nttn-section-icon" style="background:#f1f5f9;color:#475569"><i class="fas fa-sticky-note"></i></span>
                        <div>
                            <h3>Remarks</h3>
                            <p>Contract, SLA, contact person or anything else worth knowing.</p>
                        </div>
                    </div>
                    <textarea name="remarks" rows="3" class="form-control{{ $err('remarks') }}" placeholder="Optional">{{ $val('remarks') }}</textarea>
                    @error('remarks') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

            </div>
        </div>

        {{-- ================= Side ================= --}}
        <div class="col-lg-4">
            <div class="card nttn-card nttn-side">
                <div class="nttn-section">
                    <div class="nttn-section-head">
                        <span class="nttn-section-icon" style="background:#dcfce7;color:#16a34a"><i class="fas fa-heartbeat"></i></span>
                        <div>
                            <h3>Monitoring</h3>
                            <p>Pinged automatically every minute; status shows UP or DOWN.</p>
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <label for="ping_ip">Ping IP</label>
                        <input type="text" id="ping_ip" name="ping_ip" class="form-control mono{{ $err('ping_ip') }}"
                               value="{{ $val('ping_ip') }}" placeholder="Same as Peering IP">
                        @error('ping_ip') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <small class="form-text text-muted">The system pings this every minute. Leave blank to use the Peering IP.</small>
                    </div>

                    <div class="nttn-alert-note {{ $telegramOn ? 'on' : 'off' }}">
                        @if ($telegramOn)
                            <i class="fab fa-telegram-plane"></i>
                            Down/up alerts go to Telegram with the <strong>Link ID</strong> and <strong>Location</strong>.
                        @else
                            <i class="fas fa-bell-slash"></i>
                            Telegram NTTN alerts are off — the link will still be checked, but no message is sent.
                            @can('access-settings-telegram')
                                <a href="{{ route('settings.telegram') }}">Turn on</a>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>

            @if ($nttnLink->exists && $nttnLink->last_ping_at)
                <div class="card nttn-card nttn-side">
                    <div class="nttn-section">
                        <div class="small text-muted text-uppercase font-weight-bold mb-2">Current status</div>
                        @include('nttn_links._ping', ['link' => $nttnLink])
                    </div>
                </div>
            @endif
        </div>

    </div>

    <div class="nttn-actions">
        <a href="{{ $nttnLink->exists ? route('nttn-links.show', $nttnLink) : route('nttn-links.index') }}" class="btn btn-light">Cancel</a>
        <button type="submit" class="btn btn-primary" id="nttn-submit">
            <i class="fas fa-save"></i> {{ $submitLabel }}
        </button>
    </div>
</form>

@section('css')
<style>
    .nttn-card { border-radius: .9rem; }
    .nttn-section { padding: 1.35rem 1.5rem; border-bottom: 1px solid #eef2f7; }
    .nttn-section:last-child { border-bottom: 0; }
    .nttn-section-head { display: flex; gap: .85rem; align-items: flex-start; margin-bottom: 1.1rem; }
    .nttn-section-head h3 { margin: 0; font-size: 1rem; font-weight: 700; color: #0f172a; }
    .nttn-section-head p { margin: .1rem 0 0; font-size: .82rem; color: #64748b; }
    .nttn-section-icon { flex: none; display: grid; place-items: center; width: 2.2rem; height: 2.2rem; border-radius: .6rem;
        background: #eef2ff; color: #4f46e5; font-size: .9rem; }
    .nttn-card label { font-size: .84rem; font-weight: 600; color: #334155; }
    .nttn-card .req { color: #e11d48; }
    .nttn-card .mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .9rem; }
    .nttn-status .btn { font-size: .85rem; }
    .nttn-side { position: relative; }
    @media (min-width: 992px) { .nttn-side:first-child { position: sticky; top: 4.5rem; } }
    .nttn-alert-note { margin-top: .9rem; padding: .65rem .8rem; border-radius: .6rem; font-size: .8rem; line-height: 1.45; }
    .nttn-alert-note.on { background: #ecfeff; color: #0e7490; }
    .nttn-alert-note.off { background: #fff7ed; color: #9a3412; }
    .nttn-actions { position: sticky; bottom: 0; z-index: 5; display: flex; justify-content: flex-end; gap: .5rem;
        margin: 0 -.5rem 1rem; padding: .85rem 1rem; background: rgba(244, 246, 251, .92); backdrop-filter: blur(6px);
        border-top: 1px solid #e2e8f0; }
    .nttn-actions .btn { min-width: 7rem; }
</style>
@stop

@section('js')
<script>
$(function () {
    $('.select2-zone').select2({ theme: 'bootstrap4', width: '100%' });

    document.getElementById('nttn-form').addEventListener('submit', function () {
        var s = document.getElementById('nttn-submit');
        s.disabled = true;
        s.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    });
});
</script>
@stop
