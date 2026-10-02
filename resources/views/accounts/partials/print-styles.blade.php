<style>
    @page {
        size: A4;
        margin: 15mm;
    }

    :root {
        --ink: #0f172a;
        --ink-soft: #475569;
        --brand: #4f46e5;
        --border: #e2e8f0;
    }

    * { box-sizing: border-box; }

    body {
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        color: var(--ink);
        background: #f4f6fb;
        margin: 0;
        padding: 2rem 1rem;
    }

    .invoice-sheet {
        max-width: 720px;
        margin: 0 auto 2rem;
        background: #fff;
        border-radius: 0.75rem;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        padding: 2.5rem;
    }

    .head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 1.25rem;
        border-bottom: 2px solid var(--border);
        padding-bottom: 1.25rem;
        margin-bottom: 1.5rem;
    }

    .brand {
        display: flex;
        gap: 0.9rem;
        align-items: flex-start;
    }

    .brand img {
        width: 64px;
        height: 64px;
        object-fit: contain;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        background: #fff;
        flex-shrink: 0;
    }

    .brand .company {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--ink);
    }

    .brand .company-meta {
        margin-top: 0.2rem;
        color: var(--ink-soft);
        font-size: 0.85rem;
        line-height: 1.5;
    }

    .titlebox {
        text-align: right;
        flex-shrink: 0;
    }

    .titlebox .title {
        font-size: 1.4rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        color: var(--brand);
    }

    .titlebox .subid {
        margin-top: 0.25rem;
        color: var(--ink-soft);
        font-size: 0.85rem;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .info-card {
        border: 1px solid var(--border);
        border-radius: 0.6rem;
        padding: 0.9rem 1rem;
        background: #f8fafc;
    }

    .info-card h4 {
        margin: 0 0 0.6rem;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--ink-soft);
    }

    .kv {
        display: grid;
        grid-template-columns: 110px 1fr;
        gap: 0.35rem 0.6rem;
        font-size: 0.85rem;
        line-height: 1.4;
    }

    .kv .k {
        color: var(--ink-soft);
        font-weight: 600;
    }

    .status-badge {
        display: inline-block;
        margin-top: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 50rem;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.03em;
    }

    .status-paid { background: #dcfce7; color: #166534; }
    .status-unpaid { background: #fee2e2; color: #991b1b; }
    .status-partial { background: #fef3c7; color: #92400e; }

    .amount-in-words {
        font-size: 0.85rem;
        color: var(--ink-soft);
        font-style: italic;
        margin: 0 0 1.5rem;
    }

    table.items {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid var(--border);
        margin-bottom: 1.5rem;
    }

    table.items th,
    table.items td {
        border: 1px solid var(--border);
        padding: 0.55rem 0.6rem;
        vertical-align: top;
    }

    table.items thead th {
        text-align: left;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--ink-soft);
        background: #f3f6fb;
    }

    table.items td.amount, table.items th.amount {
        text-align: right;
        white-space: nowrap;
    }

    .remark-note {
        margin-top: 0.15rem;
        font-size: 0.72rem;
        font-weight: 400;
        color: var(--ink-soft);
        font-style: italic;
    }

    .adjustment-tag {
        display: inline-block;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: var(--ink-soft);
        background: #f1f5f9;
        border-radius: 0.25rem;
        padding: 0.1rem 0.4rem;
        margin-left: 0.4rem;
    }

    .summary {
        display: grid;
        grid-template-columns: 1fr 280px;
        gap: 1rem;
        align-items: start;
        margin-bottom: 1.5rem;
    }

    .payments-card h4 {
        margin: 0 0 0.6rem;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--ink-soft);
    }

    table.payments-mini {
        width: 100%;
        border-collapse: collapse;
    }

    table.payments-mini th,
    table.payments-mini td {
        padding: 0.35rem 0;
        font-size: 0.8rem;
        border-bottom: 1px solid var(--border);
    }

    table.payments-mini th {
        text-align: left;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        font-size: 0.68rem;
        color: var(--ink-soft);
    }

    table.payments-mini td.amount, table.payments-mini th.amount {
        text-align: right;
    }

    .totals-box {
        border: 1px solid var(--border);
        border-radius: 0.6rem;
        overflow: hidden;
    }

    .totals-box table {
        width: 100%;
        border-collapse: collapse;
    }

    .totals-box td {
        padding: 0.55rem 0.75rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.85rem;
    }

    .totals-box tr:last-child td {
        border-bottom: 0;
    }

    .totals-box td.label {
        color: var(--ink-soft);
        font-weight: 600;
    }

    .totals-box td.amount {
        text-align: right;
    }

    .totals-box tr.grand td {
        font-size: 1rem;
        font-weight: 800;
    }

    .footer-note {
        margin-top: 2rem;
        font-size: 0.8rem;
        color: var(--ink-soft);
        border-top: 1px solid var(--border);
        padding-top: 1rem;
    }

    .print-actions {
        max-width: 720px;
        margin: 0 auto 1rem;
        text-align: right;
    }

    .print-actions button {
        background: var(--brand);
        color: #fff;
        border: none;
        border-radius: 0.4rem;
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
        cursor: pointer;
    }

    .batch-sheet-wrapper:not(:last-child) {
        page-break-after: always;
    }

    .adjustments-panel {
        max-width: 720px;
        margin: 0 auto 2rem;
        background: #fff;
        border-radius: 0.75rem;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        padding: 1.5rem 2.5rem;
    }

    .adjustments-panel h3 {
        font-size: 0.95rem;
        margin: 0 0 1rem;
    }

    .adjustments-panel table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1rem;
    }

    .adjustments-panel table th {
        text-align: left;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--ink-soft);
        border-bottom: 1px solid var(--border);
        padding: 0.4rem 0;
    }

    .adjustments-panel table td {
        padding: 0.4rem 0;
        border-bottom: 1px solid var(--border);
        font-size: 0.85rem;
    }

    .adjustments-panel table td.amount,
    .adjustments-panel table th.amount {
        text-align: right;
    }

    .adjustments-panel table td.actions,
    .adjustments-panel table th.actions {
        text-align: right;
        white-space: nowrap;
    }

    .adjustments-panel form.add-adjustment {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .adjustments-panel input[type="text"],
    .adjustments-panel input[type="number"] {
        border: 1px solid var(--border);
        border-radius: 0.4rem;
        padding: 0.45rem 0.6rem;
        font-size: 0.85rem;
    }

    .adjustments-panel input[type="text"] { flex: 1 1 220px; }
    .adjustments-panel input[type="number"] { flex: 0 1 140px; }

    .adjustments-panel button,
    .adjustments-panel .btn-remove {
        background: var(--brand);
        color: #fff;
        border: none;
        border-radius: 0.4rem;
        padding: 0.45rem 1rem;
        font-size: 0.85rem;
        cursor: pointer;
    }

    .adjustments-panel .btn-remove {
        background: #dc2626;
        padding: 0.2rem 0.6rem;
        font-size: 0.75rem;
    }

    .locked-note {
        font-size: 0.85rem;
        color: var(--ink-soft);
    }

    @media print {
        body { background: #fff; padding: 0; }
        .invoice-sheet { box-shadow: none; border-radius: 0; padding: 0; margin-bottom: 0; }
        .print-actions, .adjustments-panel { display: none; }
        .head { display: flex !important; flex-direction: row !important; }
        .info-grid { display: grid !important; grid-template-columns: 1fr 1fr !important; }
        .summary { display: grid !important; grid-template-columns: 1fr 280px !important; }
        .status-badge { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    }

    @media (max-width: 640px) {
        .head, .info-grid, .summary { grid-template-columns: 1fr; display: grid; }
        .head { flex-direction: column; }
        .titlebox { text-align: left; }
    }
</style>
