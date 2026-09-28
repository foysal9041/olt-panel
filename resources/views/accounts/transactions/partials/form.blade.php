@php
    $transaction = $transaction ?? null;
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
        <select name="transaction_category_id" class="form-control" required>
            <option value="">Select a category</option>
            <optgroup label="Income">
                @foreach($categories->where('type', 'income') as $category)
                    <option value="{{ $category->id }}" {{ old('transaction_category_id', $transaction->transaction_category_id ?? '') == $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </optgroup>
            <optgroup label="Expense">
                @foreach($categories->where('type', 'expense') as $category)
                    <option value="{{ $category->id }}" {{ old('transaction_category_id', $transaction->transaction_category_id ?? '') == $category->id ? 'selected' : '' }}>
                        {{ $category->name }}
                    </option>
                @endforeach
            </optgroup>
        </select>
        <small class="text-muted">
            Need a new category? Add one from the <a href="{{ route('accounts.transactions.index') }}">Transactions</a> page.
        </small>
    </div>

    <div class="form-group">
        <label>Amount (&#2547;)</label>
        <input type="number" step="0.01" min="0.01" name="amount" class="form-control"
               value="{{ old('amount', $transaction->amount ?? '') }}" required>
    </div>

    <div class="form-group">
        <label>Date</label>
        <input type="date" name="transaction_date" class="form-control"
               value="{{ old('transaction_date', $transaction ? $transaction->transaction_date->toDateString() : now()->toDateString()) }}" required>
    </div>

    <div class="form-group">
        <label>Description / Purpose</label>
        <input type="text" name="description" class="form-control"
               value="{{ old('description', $transaction->description ?? '') }}"
               placeholder="e.g. Office rent for August">
    </div>

</div>
