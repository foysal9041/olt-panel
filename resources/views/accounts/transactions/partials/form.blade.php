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
                    <option value="{{ $category->id }}" data-real-income="{{ \App\Models\Transaction::isRealIncome($category) ? 1 : 0 }}" {{ old('transaction_category_id', $transaction->transaction_category_id ?? '') == $category->id ? 'selected' : '' }}>
                        {{ $category->displayName() }}
                    </option>
                @endforeach
            </optgroup>
            <optgroup label="Expense">
                @foreach($categories->where('type', 'expense') as $category)
                    <option value="{{ $category->id }}" {{ old('transaction_category_id', $transaction->transaction_category_id ?? '') == $category->id ? 'selected' : '' }}>
                        {{ $category->displayName() }}
                    </option>
                @endforeach
            </optgroup>
        </select>
        <small class="text-muted">
            Need a new category? Add one from the <a href="{{ route('accounts.transactions.index') }}">Transactions</a> page.
        </small>
    </div>

    <div class="form-group">
        <label>Money moved</label>
        <div class="btn-group btn-group-toggle d-flex" data-toggle="buttons">
            @foreach (\App\Models\Transaction::ACCOUNTS as $key => $label)
                @php $on = old('account', $transaction->account ?? 'cash') === $key; @endphp
                <label class="btn btn-outline-primary flex-fill {{ $on ? 'active' : '' }}">
                    <input type="radio" name="account" value="{{ $key }}" autocomplete="off" @checked($on)>
                    <i class="fas {{ $key === 'cash' ? 'fa-wallet' : 'fa-university' }}"></i> {{ $label }}
                </label>
            @endforeach
        </div>
        <small class="text-muted">All income goes to the <strong>Bank</strong>. <strong>Petty cash</strong> = the cash the office gets from the bank for its expenses (Cash Book) — pick it for expenses paid out of it.</small>
    </div>

    <div class="form-group">
        <label>Amount (&#2547;)</label>
        <input type="number" step="0.01" min="0.01" name="amount" class="form-control"
               value="{{ old('amount', $transaction->amount ?? '') }}" required>
    </div>

    <div class="form-group">
        <label>Date</label>
        <input type="date" name="transaction_date" class="form-control"
               @unless (auth()->user()->isAdmin()) min="{{ now()->toDateString() }}" @endunless
               value="{{ old('transaction_date', $transaction ? $transaction->transaction_date->toDateString() : now()->toDateString()) }}" required>
    </div>

    <div class="form-group">
        <label>Description / Purpose</label>
        <input type="text" name="description" class="form-control"
               value="{{ old('description', $transaction->description ?? '') }}"
               placeholder="e.g. Office rent for August">
    </div>

</div>

<script>
// All income is deposited in the bank: picking an income head selects Bank.
(function () {
    var select = document.querySelector('select[name="transaction_category_id"]');
    if (!select) return;
    function sync() {
        var opt = select.options[select.selectedIndex], real = opt && opt.dataset.realIncome === '1';
        document.querySelectorAll('input[name="account"]').forEach(function (r) {
            r.disabled = real && r.value === 'cash';
            if (real && r.value === 'bank') { r.checked = true; }
            r.closest('label').classList.toggle('active', r.checked);
            r.closest('label').classList.toggle('disabled', r.disabled);
        });
    }
    select.addEventListener('change', sync);
    sync();
})();
</script>
