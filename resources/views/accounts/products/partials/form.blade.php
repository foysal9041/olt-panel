@php
    $product = $product ?? null;
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
        <label>Category</label>
        <select name="product_category_id" class="form-control" required>
            <option value="">Select a category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" {{ old('product_category_id', $product->product_category_id ?? '') == $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        <small class="text-muted">
            Need a new category? Add one from the <a href="{{ route('accounts.products.index') }}">Products</a> page.
        </small>
    </div>

    <div class="form-group">
        <label>Product Name</label>
        <input type="text" name="name" class="form-control"
               value="{{ old('name', $product->name ?? '') }}"
               placeholder="e.g. 10 Mbps Home Package" required>
    </div>

    <div class="form-group">
        <label>Price (&#2547;)</label>
        <input type="number" step="0.01" min="0" name="price" class="form-control"
               value="{{ old('price', $product->price ?? '') }}" required>
    </div>

    <div class="form-group">
        <label>Billing Cycle</label>
        <select name="billing_cycle" class="form-control" required>
            <option value="monthly" {{ old('billing_cycle', $product->billing_cycle ?? 'monthly') == 'monthly' ? 'selected' : '' }}>
                Monthly (recurring)
            </option>
            <option value="one_time" {{ old('billing_cycle', $product->billing_cycle ?? '') == 'one_time' ? 'selected' : '' }}>
                One-time
            </option>
        </select>
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $product->description ?? '') }}</textarea>
    </div>

    <div class="form-group form-check">
        <input type="checkbox" name="status" id="status" class="form-check-input" value="1"
               {{ old('status', $product->status ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="status">Active (available for new customers)</label>
    </div>

</div>
