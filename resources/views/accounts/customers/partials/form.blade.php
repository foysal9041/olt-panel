@php
    $customer = $customer ?? null;
    $customerRates = $customerRates ?? collect();
@endphp

<div class="card-body">

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="form-row">
        <div class="col-md-7 form-group">
            <label>Name</label>
            <input type="text" name="name" class="form-control"
                   value="{{ old('name', $customer->name ?? '') }}" required autofocus>
        </div>
        <div class="col-md-5 form-group">
            <label>Username</label>
            <input type="text" name="username" class="form-control"
                   value="{{ old('username', $customer->username ?? '') }}" placeholder="e.g. strkamrul" autocomplete="off">
        </div>
    </div>

    <div class="form-row">
        <div class="col-md-5 form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control"
                   value="{{ old('phone', $customer->phone ?? '') }}" placeholder="01XXXXXXXXX">
        </div>
        <div class="col-md-7 form-group">
            <label>Zone</label>
            <select name="zone" class="form-control select2-zone">
                <option value="">Unassigned</option>
                @foreach($zones as $zone)
                    <option value="{{ $zone }}" {{ old('zone', $customer->zone ?? '') == $zone ? 'selected' : '' }}>
                        {{ $zone }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-group">
        <label>Address</label>
        <input type="text" name="address" class="form-control"
               value="{{ old('address', $customer->address ?? '') }}">
    </div>

    <div class="form-group">
        <label>Customer Type</label>
        <select name="customer_type" id="customer_type" class="form-control">
            @foreach (\App\Models\Customer::TYPES as $value => $label)
                <option value="{{ $value }}" @selected(old('customer_type', $customer->customer_type ?? 'mac_client') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div id="mac-client-fields">
        <div class="alert alert-light border small mb-3">
            <i class="fas fa-info-circle text-primary mr-1"></i>
            MAC clients are billed through <strong>Zone Settlement</strong> — the monthly Excel is matched to them by username.
        </div>
    </div>

    <div id="bandwidth-client-fields" class="d-none">

        <div class="form-row">
            <div class="col-md-6 form-group">
                <label>Contact person <small class="text-muted">(invoice "To" / Received By)</small></label>
                <input type="text" name="contact_person" class="form-control" maxlength="100"
                       value="{{ old('contact_person', $customer->contact_person ?? '') }}" placeholder="e.g. KH Delwar Hossain">
            </div>
            <div class="col-md-6 form-group">
                <label>Due before billing here started</label>
                <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">৳</span></div>
                    <input type="number" step="0.01" name="opening_due" class="form-control"
                           value="{{ old('opening_due', isset($customer) && (float) $customer->opening_due ? rtrim(rtrim($customer->opening_due, '0'), '.') : '') }}" placeholder="0"></div>
                <small class="text-muted">Shows as previous due on the first invoice.</small>
            </div>
        </div>

        <div class="form-row">
            <div class="col-md-6 form-group">
                <label>KAM Name</label>
                <input type="text" name="kam_name" class="form-control" value="{{ old('kam_name', $customer->kam_name ?? '') }}">
            </div>
            <div class="col-md-6 form-group">
                <label>KAM Phone</label>
                <input type="text" name="kam_phone" class="form-control" value="{{ old('kam_phone', $customer->kam_phone ?? '') }}">
            </div>
        </div>

        <h6 class="text-muted text-uppercase small font-weight-bold mb-2 mt-2">Rates &amp; Mbps <span class="text-lowercase font-weight-normal">— billed flat monthly: Rate × Mbps</span></h6>

        @if($bandwidthTypes->isEmpty())
            <p class="text-muted">No bandwidth types yet — add one from the <a href="{{ route('accounts.customers.index') }}">Customers</a> page first.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2">
                    <thead><tr><th>Type</th><th>Rate (&#2547; per Mbps)</th><th>Mbps</th></tr></thead>
                    <tbody>
                        @foreach($bandwidthTypes as $type)
                            @php $existing = $customerRates[$type->id] ?? null; @endphp
                            <tr>
                                <td class="align-middle">{{ $type->name }} @if ($type->flat)<small class="text-muted">(fixed amount — Rate only, or Rate × Mbps)</small>@endif</td>
                                <td><input type="number" step="any" min="0" name="bandwidth_rates[{{ $type->id }}][rate]" class="form-control"
                                           value="{{ old('bandwidth_rates.' . $type->id . '.rate', $existing ? rtrim(rtrim($existing->rate, '0'), '.') : '') }}"></td>
                                <td><input type="number" step="any" min="0" name="bandwidth_rates[{{ $type->id }}][quantity]" class="form-control"
                                           value="{{ old('bandwidth_rates.' . $type->id . '.quantity', $existing && ! ($type->flat && (float) $existing->quantity == 1) ? rtrim(rtrim($existing->quantity, '0'), '.') : '') }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-row align-items-end">
                <div class="col-md-5 form-group mb-1">
                    <label>Changes take effect from</label>
                    <input type="date" name="rates_from" class="form-control" value="{{ old('rates_from', now()->toDateString()) }}">
                </div>
                <div class="col-md-7 form-group mb-1">
                    <small class="text-muted">Rate revised, upgraded or downgraded? Change the numbers and pick the day it starts — that month is billed in two parts. Leave a rate blank to stop that type.</small>
                </div>
            </div>
        @endif

    </div>

    <div class="form-group form-check">
        <input type="checkbox" name="status" id="status" class="form-check-input" value="1"
               {{ old('status', $customer->status ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="status">Active customer</label>
    </div>

</div>

<script>
(function () {
    var typeSelect = document.getElementById('customer_type');
    var macFields = document.getElementById('mac-client-fields');
    var bandwidthFields = document.getElementById('bandwidth-client-fields');

    function toggleFields() {
        var type = typeSelect.value;
        var showBandwidth = type === 'bandwidth_client';
        var showPackage = !showBandwidth;
        macFields.classList.toggle('d-none', !showPackage);
        bandwidthFields.classList.toggle('d-none', !showBandwidth);
    }

    typeSelect.addEventListener('change', toggleFields);
    toggleFields();
})();
</script>
