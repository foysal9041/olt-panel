<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoices — {{ $month->format('F Y') }} — Sunlit Network</title>

    @include('accounts.invoices.partials.print-styles')
</head>
<body>

    <div class="print-actions">
        <button onclick="window.print()">Print All ({{ $invoices->count() }})</button>
    </div>

    @forelse($invoices as $invoice)
        <div class="batch-sheet-wrapper">
            @include('accounts.invoices.partials.sheet', ['invoice' => $invoice])
        </div>
    @empty
        <div class="invoice-sheet">
            <p>No invoices found for {{ $month->format('F Y') }}.</p>
        </div>
    @endforelse

</body>
</html>
