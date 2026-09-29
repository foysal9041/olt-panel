{{-- MAC client: allowed packages (rate / commission), goods handed over, monthly invoice. --}}
@php
    $tk = fn ($v) => '৳' . number_format((float) $v, 2);
    $packages = $customer->packages->sortBy(fn ($p) => [! $p->is_active, $p->product->name])->values();
    $pendingGoods = $customer->givenProducts->filter->isPending()->values();
    $canInvoice = auth()->user()->can('access-accounts-invoices');
@endphp

<div class="row">

<div class="col-xl-7">
<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-box-open mr-1"></i> Allowed Packages</h3>
        <div class="card-tools small text-muted">Rate per user — fixed, or list price less commission</div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 text-center">
            <thead class="thead-light">
                <tr>
                    <th>SL</th>
                    <th class="text-left">Package</th>
                    <th>List Price</th>
                    <th>Pricing</th>
                    <th>Rate / user</th>
                    <th>Users</th>
                    <th>Monthly</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($packages as $i => $package)
                <tr class="{{ $package->is_active ? '' : 'text-muted' }}">
                    <td>{{ $i + 1 }}</td>
                    <td class="text-left">
                        {{ $package->product->name }}
                        @unless($package->is_active)<span class="badge badge-secondary ml-1">Paused</span>@endunless
                    </td>
                    <td>{{ $tk($package->product->price) }}</td>
                    <td>
                        @if($package->pricing === 'commission')
                            <span class="badge badge-warning">{{ rtrim(rtrim(number_format($package->commission_percent, 2), '0'), '.') }}% commission</span>
                        @else
                            <span class="badge badge-info">Fixed rate</span>
                        @endif
                    </td>
                    <td class="font-weight-bold">{{ $tk($package->unitPrice()) }}</td>
                    <td>{{ number_format($package->quantity) }}</td>
                    <td class="font-weight-bold">{{ $tk($package->monthlyTotal()) }}</td>
                    <td class="text-nowrap">
                        <button type="button" class="btn btn-xs btn-outline-primary" data-toggle="collapse" data-target="#pkg-edit-{{ $package->id }}" title="Edit"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('accounts.customers.packages.destroy', [$customer, $package]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove {{ e($package->product->name) }} from this client?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-outline-danger" title="Remove"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <tr class="collapse bg-light" id="pkg-edit-{{ $package->id }}">
                    <td colspan="8" class="text-left">
                        <form action="{{ route('accounts.customers.packages.update', [$customer, $package]) }}" method="POST" class="form-row align-items-end pkg-form px-2 py-1">
                            @csrf @method('PUT')
                            <input type="hidden" name="product_id" value="{{ $package->product_id }}">
                            <div class="col-md-3 form-group mb-1">
                                <label class="small mb-0">Pricing</label>
                                <select name="pricing" class="form-control form-control-sm pkg-pricing">
                                    <option value="fixed" @selected($package->pricing === 'fixed')>Fixed rate</option>
                                    <option value="commission" @selected($package->pricing === 'commission')>Commission %</option>
                                </select>
                            </div>
                            <div class="col-md-2 form-group mb-1 pkg-rate">
                                <label class="small mb-0">Rate / user ৳</label>
                                <input type="number" step="0.01" min="0" name="rate" value="{{ $package->rate }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2 form-group mb-1 pkg-commission">
                                <label class="small mb-0">Commission %</label>
                                <input type="number" step="0.01" min="0" max="100" name="commission_percent" value="{{ $package->commission_percent }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2 form-group mb-1">
                                <label class="small mb-0">Users</label>
                                <input type="number" min="0" name="quantity" value="{{ $package->quantity }}" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2 form-group mb-1">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="pkg-active-{{ $package->id }}" name="is_active" value="1" @checked($package->is_active)>
                                    <label class="custom-control-label small" for="pkg-active-{{ $package->id }}">Active</label>
                                </div>
                            </div>
                            <div class="col-md-1 form-group mb-1">
                                <button class="btn btn-sm btn-primary btn-block"><i class="fas fa-save"></i></button>
                            </div>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-muted py-3">No packages allowed yet — add one below.</td></tr>
            @endforelse
            </tbody>
            @if($packages->where('is_active', true)->isNotEmpty())
                <tfoot>
                    <tr class="font-weight-bold">
                        <td colspan="5" class="text-right">Total</td>
                        <td>{{ number_format($packages->where('is_active', true)->sum('quantity')) }}</td>
                        <td>{{ $tk($packages->where('is_active', true)->sum(fn ($p) => $p->monthlyTotal())) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
        </div>
    </div>

    <div class="card-footer">
        @if($packageOptions->isEmpty())
            <span class="text-muted small">
                No monthly packages in the product list yet.
                <a href="{{ route('accounts.products.create') }}">Add a package</a> (Billing cycle: Monthly) first.
            </span>
        @else
            <form action="{{ route('accounts.customers.packages.store', $customer) }}" method="POST" class="form-row align-items-end pkg-form">
                @csrf
                <div class="col-md-4 form-group mb-1">
                    <label class="small mb-0">Package</label>
                    <select name="product_id" class="form-control form-control-sm" required>
                        <option value="">Choose…</option>
                        @foreach($packageOptions as $option)
                            <option value="{{ $option->id }}" @disabled($packages->contains('product_id', $option->id)) @selected(old('product_id') == $option->id)>
                                {{ $option->name }} — {{ $tk($option->price) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group mb-1">
                    <label class="small mb-0">Pricing</label>
                    <select name="pricing" class="form-control form-control-sm pkg-pricing">
                        <option value="fixed" @selected(old('pricing') !== 'commission')>Fixed rate</option>
                        <option value="commission" @selected(old('pricing') === 'commission')>Commission %</option>
                    </select>
                </div>
                <div class="col-md-2 form-group mb-1 pkg-rate">
                    <label class="small mb-0">Rate / user ৳</label>
                    <input type="number" step="0.01" min="0" name="rate" value="{{ old('rate') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 form-group mb-1 pkg-commission">
                    <label class="small mb-0">Commission %</label>
                    <input type="number" step="0.01" min="0" max="100" name="commission_percent" value="{{ old('commission_percent') }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 form-group mb-1">
                    <label class="small mb-0">Users</label>
                    <input type="number" min="0" name="quantity" value="{{ old('quantity', 0) }}" class="form-control form-control-sm">
                </div>
                <div class="col-md-2 form-group mb-1">
                    <button class="btn btn-sm btn-primary btn-block"><i class="fas fa-plus"></i> Allow</button>
                </div>
            </form>
        @endif
    </div>
</div>
</div>

<div class="col-xl-5">
<div class="card card-outline card-warning">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-dolly mr-1"></i> Products Given</h3>
        <div class="card-tools small text-muted">Chargeable goods go on the next invoice</div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 text-center">
            <thead class="thead-light">
                <tr><th>Date</th><th class="text-left">Product</th><th>Qty</th><th>Total</th><th>Billing</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($customer->givenProducts as $goods)
                <tr>
                    <td class="text-nowrap">{{ $goods->given_on->format('d M Y') }}</td>
                    <td class="text-left">
                        {{ $goods->name }}
                        @if($goods->note)<div class="small text-muted">{{ $goods->note }}</div>@endif
                    </td>
                    <td>{{ $goods->quantity }}</td>
                    <td>{{ $goods->chargeable ? $tk($goods->total()) : '—' }}</td>
                    <td>
                        @if($goods->invoice_id)
                            <span class="badge badge-success">{{ $goods->invoice?->invoice_number ?? 'Billed' }}</span>
                        @elseif(! $goods->chargeable || $goods->total() <= 0)
                            <span class="badge badge-secondary">Free</span>
                        @else
                            <span class="badge badge-warning">Next invoice</span>
                        @endif
                    </td>
                    <td>
                        @unless($goods->invoice_id)
                            <form action="{{ route('accounts.customers.goods.destroy', [$customer, $goods]) }}" method="POST" onsubmit="return confirm('Remove this product entry?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-outline-danger" title="Remove"><i class="fas fa-trash"></i></button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted py-3">Nothing handed over yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>

    <div class="card-footer">
        <form action="{{ route('accounts.customers.goods.store', $customer) }}" method="POST" class="form-row align-items-end" id="goods-form">
            @csrf
            <div class="col-6 form-group mb-1">
                <label class="small mb-0">Product</label>
                <select name="product_id" class="form-control form-control-sm" id="goods-product">
                    <option value="" data-price="">Other (type name)</option>
                    @foreach($goodsOptions as $option)
                        <option value="{{ $option->id }}" data-price="{{ $option->price }}">{{ $option->name }} — {{ $tk($option->price) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 form-group mb-1">
                <label class="small mb-0">Name</label>
                <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. ONU, SFP, Patch cord" value="{{ old('name') }}">
            </div>
            <div class="col-3 form-group mb-1">
                <label class="small mb-0">Qty</label>
                <input type="number" min="1" name="quantity" value="{{ old('quantity', 1) }}" class="form-control form-control-sm" required>
            </div>
            <div class="col-4 form-group mb-1">
                <label class="small mb-0">Unit price ৳</label>
                <input type="number" step="0.01" min="0" name="unit_price" id="goods-price" value="{{ old('unit_price') }}" class="form-control form-control-sm">
            </div>
            <div class="col-5 form-group mb-1">
                <label class="small mb-0">Given on</label>
                <input type="date" name="given_on" value="{{ old('given_on', now()->toDateString()) }}" class="form-control form-control-sm" required>
            </div>
            <div class="col-7 form-group mb-1">
                <input type="text" name="note" class="form-control form-control-sm" placeholder="Note (optional)" value="{{ old('note') }}">
            </div>
            <div class="col-5 form-group mb-1">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="goods-chargeable" name="chargeable" value="1" checked>
                    <label class="custom-control-label small" for="goods-chargeable">Bill on invoice</label>
                </div>
            </div>
            <div class="col-12">
                <button class="btn btn-sm btn-warning btn-block"><i class="fas fa-plus"></i> Record Product</button>
            </div>
        </form>
    </div>
</div>
</div>

</div>

@if($canInvoice)
<div class="card card-outline card-success" id="monthly-invoice">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-invoice-dollar mr-1"></i> Monthly Invoice</h3>
        <div class="card-tools small text-muted">Users are filled in from last time — change them for this month</div>
    </div>

    <form action="{{ route('accounts.customers.invoices.monthly', $customer) }}" method="POST">
        @csrf
        <div class="card-body">
            <div class="form-row">
                <div class="col-md-3 form-group">
                    <label>Billing month</label>
                    <input type="month" name="month" value="{{ old('month', $nextMonth->format('Y-m')) }}" class="form-control" required>
                </div>
            </div>

            <table class="table table-sm table-bordered text-center mb-0" id="inv-table">
                <thead class="thead-light">
                    <tr><th class="text-left">Item</th><th style="width:9rem">Users / Qty</th><th>Rate</th><th>Amount</th></tr>
                </thead>
                <tbody>
                @foreach($packages->where('is_active', true) as $package)
                    <tr>
                        <td class="text-left">{{ $package->product->name }} <span class="small text-muted">({{ $package->pricingLabel() }})</span></td>
                        <td>
                            <input type="number" min="0" name="quantities[{{ $package->id }}]" value="{{ old('quantities.' . $package->id, $package->quantity) }}"
                                   class="form-control form-control-sm text-center inv-qty" data-rate="{{ $package->unitPrice() }}">
                        </td>
                        <td>{{ $tk($package->unitPrice()) }}</td>
                        <td class="inv-line font-weight-bold"></td>
                    </tr>
                @endforeach
                @foreach($pendingGoods as $goods)
                    <tr>
                        <td class="text-left">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input inv-goods" id="inv-goods-{{ $goods->id }}" name="products[]" value="{{ $goods->id }}" data-amount="{{ $goods->total() }}" checked>
                                <label class="custom-control-label" for="inv-goods-{{ $goods->id }}">{{ $goods->name }} <span class="small text-muted">(given {{ $goods->given_on->format('d M Y') }})</span></label>
                            </div>
                        </td>
                        <td>{{ $goods->quantity }}</td>
                        <td>{{ $tk($goods->unit_price) }}</td>
                        <td class="font-weight-bold">{{ $tk($goods->total()) }}</td>
                    </tr>
                @endforeach
                @if($packages->where('is_active', true)->isEmpty() && $pendingGoods->isEmpty())
                    <tr><td colspan="4" class="text-muted py-3">Allow a package (or record a chargeable product) to invoice this client.</td></tr>
                @endif
                </tbody>
                <tfoot>
                    <tr class="font-weight-bold" style="font-size:1.1rem">
                        <td colspan="3" class="text-right">Invoice total</td>
                        <td id="inv-total">৳0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer">
            <button class="btn btn-success" onclick="return confirm('Create the invoice for this month?')">
                <i class="fas fa-file-invoice"></i> Create Invoice
            </button>
        </div>
    </form>
</div>
@endif

<script>
(function () {
    var fmt = function (n) { return '৳' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    // Fixed rate vs commission: show only the matching input.
    document.querySelectorAll('.pkg-form').forEach(function (form) {
        var pricing = form.querySelector('.pkg-pricing');
        var sync = function () {
            var commission = pricing.value === 'commission';
            form.querySelector('.pkg-rate').style.display = commission ? 'none' : '';
            form.querySelector('.pkg-commission').style.display = commission ? '' : 'none';
        };
        pricing.addEventListener('change', sync);
        sync();
    });

    // Goods: picking a catalog product fills in its price.
    var goods = document.getElementById('goods-product');
    if (goods) {
        goods.addEventListener('change', function () {
            var price = goods.options[goods.selectedIndex].dataset.price;
            if (price) document.getElementById('goods-price').value = price;
        });
    }

    // Monthly invoice: live line amounts and total.
    var table = document.getElementById('inv-table');
    if (table) {
        var recalc = function () {
            var total = 0;
            table.querySelectorAll('.inv-qty').forEach(function (input) {
                var line = (parseInt(input.value, 10) || 0) * parseFloat(input.dataset.rate);
                input.closest('tr').querySelector('.inv-line').textContent = fmt(line);
                total += line;
            });
            table.querySelectorAll('.inv-goods:checked').forEach(function (box) { total += parseFloat(box.dataset.amount); });
            document.getElementById('inv-total').textContent = fmt(total);
        };
        table.addEventListener('input', recalc);
        table.addEventListener('change', recalc);
        recalc();
    }
})();
</script>
