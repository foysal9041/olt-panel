@extends('adminlte::page')

@section('title', 'Receive Payment')

@section('content_header')
<x-accounts.header title="Receive Payment" icon="fas fa-hand-holding-usd"
    subtitle="Pick the customer, enter what they paid — it's applied to their oldest dues first" />
@stop

@section('css')
<style>
    .pay-step { display: flex; align-items: center; gap: .6rem; margin-bottom: .9rem; }
    .pay-step b { display: grid; place-items: center; width: 1.7rem; height: 1.7rem; border-radius: 50%; background: #eef2ff; color: #4f46e5; font-size: .85rem; }
    .pay-step span { font-weight: 700; color: #0f172a; }
    .pay-due { padding: 1rem 1.2rem; border-radius: .8rem; background: linear-gradient(120deg, #fff1f2, #fff7ed); }
    .pay-due .v { font-size: 1.9rem; font-weight: 800; color: #be123c; font-variant-numeric: tabular-nums; }
    .pay-amount { font-size: 1.6rem !important; font-weight: 700; height: auto !important; padding: .45rem .8rem !important; font-variant-numeric: tabular-nums; }
    .pay-quick .btn { margin: .35rem .35rem 0 0; }
    .pay-methods .btn { margin: 0 .35rem .35rem 0; }
    .pay-table td, .pay-table th { vertical-align: middle; font-variant-numeric: tabular-nums; }
    .pay-table tr.gets td { background: #f0fdf4; }
    .pay-table .apply { font-weight: 700; color: #15803d; }
    .pay-table .left { color: #b91c1c; }
    .pay-summary { position: sticky; top: 4.5rem; }
    .pay-empty { padding: 2.5rem 1rem; text-align: center; color: #94a3b8; }
    .select2-container--bootstrap4 .select2-selection--single { height: calc(2.6rem + 2px) !important; }
    .select2-container--bootstrap4 .select2-selection__rendered { line-height: 2.5rem !important; font-size: 1rem; }
</style>
@stop

@section('content')

@if ($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif

<form method="POST" action="{{ route('accounts.payments.store') }}" id="pay-form">
    @csrf

    <div class="row">
        <div class="col-lg-7">

            <div class="card acct-panel">
                <div class="card-body">
                    <div class="pay-step"><b>1</b><span>Customer</span></div>
                    <select name="customer_id" id="customer" class="form-control" required>
                        <option value="">Search by name or phone…</option>
                        @foreach ($customers as $c)
                            <option value="{{ $c['id'] }}" @selected((int) old('customer_id', $selected) === $c['id'])
                                    data-due="{{ $c['due'] }}">
                                {{ $c['name'] }}{{ $c['phone'] ? ' · ' . $c['phone'] : '' }} — {{ $c['due'] > 0 ? 'Due ৳' . number_format($c['due']) : 'No due' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="card acct-panel">
                <div class="card-header">
                    <h3 class="card-title">Unpaid Invoices</h3>
                    <span class="small text-muted">paid oldest first</span>
                </div>
                <div class="card-body p-0">
                    <div id="no-customer" class="pay-empty"><i class="fas fa-user fa-2x mb-2 d-block"></i>Select a customer to see what they owe.</div>
                    <div id="no-due" class="pay-empty" hidden><i class="fas fa-check-circle fa-2x mb-2 d-block text-success"></i>This customer has nothing due.</div>
                    <div class="table-responsive" id="inv-wrap" hidden>
                        <table class="table pay-table mb-0">
                            <thead>
                                <tr>
                                    <th class="pl-3">Month</th>
                                    <th>Invoice</th>
                                    <th class="text-right">Bill</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Due</th>
                                    <th class="text-right">This payment</th>
                                    <th class="text-right pr-3">Left</th>
                                </tr>
                            </thead>
                            <tbody id="inv-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <div class="col-lg-5">
            <div class="pay-summary">
                <div class="card acct-panel">
                    <div class="card-body">

                        <div class="pay-due mb-3">
                            <div class="small text-muted text-uppercase font-weight-bold">Total due</div>
                            <div class="v" id="total-due">৳0</div>
                        </div>

                        <div class="pay-step"><b>2</b><span>Amount received</span></div>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text" style="font-size:1.3rem">৳</span></div>
                            <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control pay-amount @error('amount') is-invalid @enderror"
                                   value="{{ old('amount') }}" placeholder="0" required>
                        </div>
                        <div class="pay-quick" id="quick" hidden>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-fill="all">Full due</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-fill="oldest">Oldest invoice only</button>
                        </div>
                        <div class="small mt-2" id="amount-note"></div>

                        <div class="pay-step mt-4"><b>3</b><span>How was it paid?</span></div>
                        <div class="btn-group-toggle pay-methods d-flex flex-wrap" data-toggle="buttons">
                            @foreach ($methods as $m)
                                @php $on = old('method', 'Cash') === $m; @endphp
                                <label class="btn btn-sm btn-outline-secondary {{ $on ? 'active' : '' }}">
                                    <input type="radio" name="method" value="{{ $m }}" autocomplete="off" @checked($on)> {{ $m }}
                                </label>
                            @endforeach
                        </div>

                        <div class="row mt-2">
                            <div class="col-6 form-group">
                                <label class="small font-weight-bold mb-1">Date</label>
                                <input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-6 form-group">
                                <label class="small font-weight-bold mb-1">Txn ID / Cheque no.</label>
                                <input type="text" name="reference" class="form-control" value="{{ old('reference') }}" placeholder="optional">
                            </div>
                            <div class="col-12 form-group">
                                <label class="small font-weight-bold mb-1">Note</label>
                                <input type="text" name="note" class="form-control" value="{{ old('note') }}" placeholder="optional" maxlength="150">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg btn-block" id="pay-submit" disabled>
                            <i class="fas fa-check"></i> Receive &amp; Print Receipt
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

@stop

@section('js')
<script>
$(function () {
    var customers = @json($customers->keyBy('id'));
    var $sel = $('#customer');
    var amount = document.getElementById('amount');
    var body = document.getElementById('inv-body');
    var submit = document.getElementById('pay-submit');
    var note = document.getElementById('amount-note');
    var current = null;

    function tk(v) { return '৳' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }

    $sel.select2({ theme: 'bootstrap4', width: '100%' });

    function show(id, on) { document.getElementById(id).hidden = !on; }

    function render() {
        var c = current;
        show('no-customer', !c);
        show('no-due', c && c.due <= 0);
        show('inv-wrap', c && c.due > 0);
        show('quick', c && c.due > 0);
        document.getElementById('total-due').textContent = tk(c ? c.due : 0);

        var amt = Math.max(0, parseFloat(amount.value) || 0);
        var left = amt;
        body.innerHTML = '';

        if (c && c.due > 0) {
            c.invoices.forEach(function (inv) {
                var pay = Math.min(left, inv.due);
                left = Math.round((left - pay) * 100) / 100;
                var tr = document.createElement('tr');
                if (pay > 0) tr.className = 'gets';
                tr.innerHTML =
                    '<td class="pl-3">' + (inv.month || '—') + '</td>' +
                    '<td class="small text-muted">' + inv.number + '</td>' +
                    '<td class="text-right">' + tk(inv.amount) + '</td>' +
                    '<td class="text-right text-muted">' + (inv.paid ? tk(inv.paid) : '—') + '</td>' +
                    '<td class="text-right font-weight-bold">' + tk(inv.due) + '</td>' +
                    '<td class="text-right apply">' + (pay > 0 ? tk(pay) : '—') + '</td>' +
                    '<td class="text-right pr-3 ' + (inv.due - pay > 0 ? 'left' : 'text-success') + '">' +
                        (inv.due - pay > 0 ? tk(inv.due - pay) : '<i class="fas fa-check"></i> Paid') + '</td>';
                body.appendChild(tr);
            });
        }

        var ok = c && c.due > 0 && amt > 0 && amt <= c.due + 0.001;
        submit.disabled = !ok;

        if (!c || !amt) {
            note.innerHTML = '';
        } else if (amt > c.due + 0.001) {
            note.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> More than the total due (' + tk(c.due) + ').</span>';
        } else if (amt < c.due) {
            note.innerHTML = '<span class="text-muted">Still due after this: <strong class="text-danger">' + tk(Math.round((c.due - amt) * 100) / 100) + '</strong></span>';
        } else {
            note.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Clears everything this customer owes.</span>';
        }
    }

    function selectCustomer(id, fillAmount) {
        current = customers[id] || null;
        if (current && fillAmount && current.due > 0) amount.value = current.due;
        render();
    }

    $sel.on('change', function () { selectCustomer(this.value, true); });
    amount.addEventListener('input', render);

    document.querySelectorAll('#quick [data-fill]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!current) return;
            amount.value = b.dataset.fill === 'all' ? current.due : current.invoices[0].due;
            render();
            amount.focus();
        });
    });

    document.getElementById('pay-form').addEventListener('submit', function () {
        submit.disabled = true;
        submit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
    });

    // Preselected (from a customer / invoice link) or coming back with errors.
    if ($sel.val()) selectCustomer($sel.val(), !amount.value);
    else render();
});
</script>
@stop
