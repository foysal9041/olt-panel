@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-6 form-group">
        <label>Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $switch->name) }}"
               placeholder="e.g. Core SW-1, POP Jashore" required autofocus>
    </div>

    <div class="col-md-6 form-group">
        <label>Management IP</label>
        <input type="text" name="ip" class="form-control" value="{{ old('ip', $switch->ip) }}"
               placeholder="e.g. 10.10.10.2" required>
    </div>

    <div class="col-md-4 form-group">
        <label>Vendor</label>
        <select name="vendor" class="form-control" id="vendor" required>
            @foreach (\App\Models\NetworkSwitch::VENDORS as $key => $label)
                <option value="{{ $key }}" @selected(old('vendor', $switch->vendor) === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 form-group">
        <label>Zone <small class="text-muted">(optional)</small></label>
        <select name="zone" class="form-control">
            <option value="">— None —</option>
            @foreach ($zones as $zone)
                <option value="{{ $zone }}" @selected(old('zone', $switch->zone) === $zone)>{{ $zone }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-4 form-group">
        <label>SNMP Version</label>
        <select name="snmp_version" class="form-control" required>
            <option value="2c" @selected(old('snmp_version', $switch->snmp_version) === '2c')>v2c</option>
            <option value="1" @selected(old('snmp_version', $switch->snmp_version) === '1')>v1</option>
        </select>
    </div>

    <div class="col-md-8 form-group">
        <label>SNMP Community</label>
        <input type="password" name="community" class="form-control" autocomplete="new-password"
               placeholder="{{ $switch->exists ? 'Leave blank to keep the current community' : 'e.g. public' }}"
               {{ $switch->exists ? '' : 'required' }}>
        <small class="form-text text-muted">Read-only community is enough. Stored encrypted.</small>
    </div>

    <div class="col-md-4 form-group">
        <label>SNMP Port</label>
        <input type="number" name="snmp_port" class="form-control" min="1" max="65535"
               value="{{ old('snmp_port', $switch->snmp_port) }}" required>
    </div>

    <div class="col-md-6">
        <div class="custom-control custom-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1"
                   @checked(old('is_active', $switch->is_active))>
            <label class="custom-control-label" for="is_active">Active (poll every minute)</label>
        </div>
    </div>

    <div class="col-md-6">
        <div class="custom-control custom-switch mb-2">
            <input type="hidden" name="notify" value="0">
            <input type="checkbox" class="custom-control-input" id="notify" name="notify" value="1"
                   @checked(old('notify', $switch->notify))>
            <label class="custom-control-label" for="notify">Send Telegram alerts for this switch</label>
        </div>
    </div>
</div>

@php($hasCustom = old('dom_rx_oid', $switch->dom_rx_oid) || old('dom_tx_oid', $switch->dom_tx_oid) || old('dom_temp_oid', $switch->dom_temp_oid))

<div class="mt-3">
    <a class="text-sm" data-toggle="collapse" href="#dom-advanced" role="button">
        <i class="fas fa-sliders-h"></i> Advanced: custom transceiver OIDs
    </a>
</div>

<div class="collapse {{ $hasCustom ? 'show' : '' }} mt-3" id="dom-advanced">
    <div class="callout callout-info py-2">
        <p class="mb-1 small">
            Cisco, Arista, Juniper, Huawei and MikroTik transceiver readings are detected automatically.
            BDCOM, DCN and other models are tried with the standard ENTITY-SENSOR-MIB first.
            If that shows no SFP data, enter the vendor's DOM OIDs here (base OID of each column,
            indexed by ifIndex — see the switch's MIB or ask the vendor).
        </p>
    </div>

    <div class="row">
        <div class="col-md-4 form-group">
            <label>Rx power OID</label>
            <input type="text" name="dom_rx_oid" class="form-control" value="{{ old('dom_rx_oid', $switch->dom_rx_oid) }}" placeholder="1.3.6.1.4.1....">
        </div>
        <div class="col-md-4 form-group">
            <label>Tx power OID</label>
            <input type="text" name="dom_tx_oid" class="form-control" value="{{ old('dom_tx_oid', $switch->dom_tx_oid) }}" placeholder="1.3.6.1.4.1....">
        </div>
        <div class="col-md-4 form-group">
            <label>Temperature OID</label>
            <input type="text" name="dom_temp_oid" class="form-control" value="{{ old('dom_temp_oid', $switch->dom_temp_oid) }}" placeholder="1.3.6.1.4.1....">
        </div>
        <div class="col-md-4 form-group">
            <label>Divide values by</label>
            <input type="number" name="dom_divisor" class="form-control" min="1" value="{{ old('dom_divisor', $switch->dom_divisor) }}" required>
            <small class="form-text text-muted">e.g. 100 if the switch reports -523 for -5.23</small>
        </div>
        <div class="col-md-4 form-group">
            <label>Power unit (after dividing)</label>
            <select name="dom_power_unit" class="form-control">
                @foreach (['dbm' => 'dBm', 'mw' => 'mW', 'uw' => 'µW'] as $key => $label)
                    <option value="{{ $key }}" @selected(old('dom_power_unit', $switch->dom_power_unit) === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
