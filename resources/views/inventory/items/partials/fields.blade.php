{{-- Product fields, for the new-product and edit dialogs. --}}
@php $categories = $categories ?? \App\Models\InventoryCategory::orderBy('name')->get(); @endphp
<input type="hidden" name="_form" value="item">
<div class="form-row">
    <div class="col-md-6 form-group">
        <label>Name <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $item->name) }}" class="form-control" placeholder="e.g. ONU, Fiber cable 2 core, SFP 1G LX" maxlength="255" required>
    </div>
    <div class="col-md-6 form-group">
        <label>Category</label>
        <select name="inventory_category_id" class="form-control">
            <option value="">—</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(old('inventory_category_id', $item->inventory_category_id) == $c->id)>{{ \App\Support\Ui::t($c->name) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4 form-group">
        <label>Brand</label>
        <input type="text" name="brand" value="{{ old('brand', $item->brand) }}" class="form-control" placeholder="e.g. Huawei, BDCOM" maxlength="100">
    </div>
    <div class="col-md-4 form-group">
        <label>Model</label>
        <input type="text" name="model" value="{{ old('model', $item->model) }}" class="form-control" placeholder="e.g. HG8145V5" maxlength="100">
    </div>
    <div class="col-md-4 form-group">
        <label>Unit <span class="text-danger">*</span></label>
        <input type="text" name="unit" value="{{ old('unit', $item->unit) }}" class="form-control" list="inv-units" maxlength="20" required>
        <datalist id="inv-units">@foreach (\App\Models\InventoryItem::UNITS as $u)<option value="{{ $u }}">@endforeach</datalist>
    </div>
    <div class="col-12 form-group">
        <label>What is it?</label>
        <div class="custom-control custom-radio">
            <input type="radio" id="kind-asset-{{ $item->id ?? 'new' }}" name="kind" value="asset" class="custom-control-input" @checked(old('kind', $item->kind) === 'asset')>
            <label class="custom-control-label font-weight-normal" for="kind-asset-{{ $item->id ?? 'new' }}"><b>Company asset</b> — stays the company's where it's used (OLT, switch, router, ONU given on loan, cable laid, laptop)</label>
        </div>
        <div class="custom-control custom-radio">
            <input type="radio" id="kind-cons-{{ $item->id ?? 'new' }}" name="kind" value="consumable" class="custom-control-input" @checked(old('kind', $item->kind) === 'consumable')>
            <label class="custom-control-label font-weight-normal" for="kind-cons-{{ $item->id ?? 'new' }}"><b>Consumable</b> — used up (connectors, cable ties, tape, sleeves)</label>
        </div>
    </div>
    <div class="col-md-4 form-group">
        <label>Minimum stock</label>
        <input type="number" name="min_stock" value="{{ old('min_stock', (float) $item->min_stock ?: '') }}" step="0.01" min="0" class="form-control" placeholder="0">
        <small class="form-text text-muted">Warn when the store has this many or fewer.</small>
    </div>
    <div class="col-md-4 form-group">
        <label>Usual sale price (৳)</label>
        <input type="number" name="sale_price" value="{{ old('sale_price', $item->sale_price) }}" step="0.01" min="0" class="form-control" placeholder="optional">
    </div>
    <div class="col-md-4 form-group">
        <label>Status</label>
        <div class="custom-control custom-switch mt-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" class="custom-control-input" id="active-{{ $item->id ?? 'new' }}" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))>
            <label class="custom-control-label" for="active-{{ $item->id ?? 'new' }}">Active</label>
        </div>
    </div>
    <div class="col-12 form-group mb-0">
        <label>Notes</label>
        <textarea name="notes" rows="2" class="form-control" maxlength="2000" placeholder="optional">{{ old('notes', $item->notes) }}</textarea>
    </div>
</div>
