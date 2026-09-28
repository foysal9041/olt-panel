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

    <div class="form-group">
        <label>Name</label>
        <input type="text" name="name" class="form-control"
               value="{{ old('name', $customer->name ?? '') }}" required autofocus>
    </div>

    <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" class="form-control"
               value="{{ old('phone', $customer->phone ?? '') }}">
    </div>

    <div class="form-group">
        <label>Address</label>
        <input type="text" name="address" class="form-control"
               value="{{ old('address', $customer->address ?? '') }}">
    </div>

    <div class="form-group">
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

    <div class="form-group">
        <label>Customer Type</label>
        <select name="customer_type" id="customer_type" class="form-control">
            <option value="mac_client" {{ old('customer_type', $customer->customer_type ?? 'mac_client') == 'mac_client' ? 'selected' : '' }}>
                Mac Client
            </option>
            <option value="bandwidth_client" {{ old('customer_type', $customer->customer_type ?? 'mac_client') == 'bandwidth_client' ? 'selected' : '' }}>
                Bandwidth Client
            </option>
            <option value="corporate_client" {{ old('customer_type', $customer->customer_type ?? 'mac_client') == 'corporate_client' ? 'selected' : '' }}>
                Corporate Customer
            </option>
        </select>
    </div>

    <div id="mac-client-fields">

        <div class="form-group">
            <label>Product / Package</label>
            <select name="product_id" class="form-control">
                <option value="">No package</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" {{ old('product_id', $customer->product_id ?? '') == $product->id ? 'selected' : '' }}>
                        {{ $product->name }} (&#2547;{{ number_format($product->price, 2) }} / {{ $product->billing_cycle == 'monthly' ? 'month' : 'one-time' }})
                    </option>
                @endforeach
            </select>
            <small class="text-muted">
                Drives what gets billed when generating invoices for this customer.
            </small>
        </div>

        <div class="form-group">
            <label>Package Rate (&#2547;)</label>
            <input type="number" step="0.01" min="0" name="package_rate" class="form-control"
                   value="{{ old('package_rate', $customer->package_rate ?? '') }}"
                   placeholder="Leave blank to use the package's list price">
        </div>

    </div>

    <div id="bandwidth-client-fields" class="d-none">

        <h6 class="text-muted text-uppercase small font-weight-bold mb-3">Key Account Manager (KAM)</h6>

        <div class="form-row">

            <div class="col-md-6 form-group">
                <label>KAM Name</label>
                <input type="text" name="kam_name" class="form-control"
                       value="{{ old('kam_name', $customer->kam_name ?? '') }}">
            </div>

            <div class="col-md-6 form-group">
                <label>KAM Phone</label>
                <input type="text" name="kam_phone" class="form-control"
                       value="{{ old('kam_phone', $customer->kam_phone ?? '') }}">
            </div>

        </div>

        <h6 class="text-muted text-uppercase small font-weight-bold mb-3 mt-3">Bandwidth Rates</h6>

        @if($bandwidthTypes->isEmpty())

            <p class="text-muted">
                No bandwidth types yet — add one from the
                <a href="{{ route('accounts.customers.index') }}">Customers</a> page first.
            </p>

        @else

            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Rate (&#2547; / Mbps)</th>
                            <th>Quantity (Mbps)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bandwidthTypes as $type)
                            @php
                                $existing = $customerRates[$type->id] ?? null;
                            @endphp
                            <tr>
                                <td class="align-middle">{{ $type->name }}</td>
                                <td>
                                    <input type="number" step="0.01" min="0"
                                           name="bandwidth_rates[{{ $type->id }}][rate]" class="form-control"
                                           value="{{ old('bandwidth_rates.' . $type->id . '.rate', $existing->rate ?? '') }}">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0"
                                           name="bandwidth_rates[{{ $type->id }}][quantity]" class="form-control"
                                           value="{{ old('bandwidth_rates.' . $type->id . '.quantity', $existing->quantity ?? '') }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <small class="text-muted">Leave rate blank to skip that type. Quantity defaults to 1 if left blank.</small>

        @endif

    </div>

    <div class="form-group form-check">
        <input type="checkbox" name="status" id="status" class="form-check-input" value="1"
               {{ old('status', $customer->status ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="status">Active (included when generating invoices)</label>
    </div>

</div>

<script>
(function () {
    var typeSelect = document.getElementById('customer_type');
    var macFields = document.getElementById('mac-client-fields');
    var bandwidthFields = document.getElementById('bandwidth-client-fields');

    function toggleFields() {
        var type = typeSelect.value;
        var showPackage = type === 'mac_client' || type === 'corporate_client';
        var showBandwidth = type === 'bandwidth_client' || type === 'corporate_client';
        macFields.classList.toggle('d-none', !showPackage);
        bandwidthFields.classList.toggle('d-none', !showBandwidth);
    }

    typeSelect.addEventListener('change', toggleFields);
    toggleFields();
})();
</script>
