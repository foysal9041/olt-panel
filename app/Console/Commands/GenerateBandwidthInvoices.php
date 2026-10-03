<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\ProfitSheet;
use App\Services\BandwidthBilling;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Makes the month's invoice for every active bandwidth client that doesn't
 * have one yet (safe to run again). Not scheduled — invoices are made by
 * hand from Bandwidth Billing; this is for doing it from the shell.
 */
class GenerateBandwidthInvoices extends Command
{
    protected $signature = 'app:generate-bandwidth-invoices {--month= : Y-m, default this month}';

    protected $description = 'Make this month\'s invoice for every active bandwidth client';

    public function handle(BandwidthBilling $billing): int
    {
        $month = $this->option('month') ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth() : Carbon::today()->startOfMonth();

        if (ProfitSheet::isMonthClosed($month)) {
            $this->warn("{$month->format('F Y')} is closed — nothing made.");

            return self::SUCCESS;
        }

        $customers = Customer::where('customer_type', 'bandwidth_client')->where('status', true)
            ->whereDoesntHave('bandwidthInvoices', fn ($q) => $q->whereDate('month', $month))->get();

        foreach ($customers as $customer) {
            $invoice = $billing->generate($customer, $month);
            $this->line("{$invoice->invoice_no}  {$customer->name}  ৳" . number_format((float) $invoice->load('lines')->totalBill(), 2));
        }

        $this->info($customers->isEmpty() ? 'Nothing to make.' : "Made {$customers->count()} invoice(s) for {$month->format('F Y')}.");

        return self::SUCCESS;
    }
}
