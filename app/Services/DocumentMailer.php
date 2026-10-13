<?php

namespace App\Services;

use App\Mail\DocumentMail;
use App\Models\ActivityLog;
use App\Models\BandwidthInvoice;
use App\Models\BandwidthPayment;
use App\Models\Customer;
use App\Models\MailSetting;
use App\Models\SentMail;
use App\Models\ZoneSettlementRow;
use App\Support\Dec;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Illuminate\Support\defer;

/**
 * Emails invoices and money receipts to clients, as PDFs, from the
 * office mailbox set on Settings → Email. Every attempt is kept as a
 * SentMail on the document, so its page shows whether it went out.
 */
class DocumentMailer
{
    private ?MailSetting $settings = null;

    public function __construct(private PdfMaker $pdf)
    {
    }

    public function settings(): MailSetting
    {
        return $this->settings ??= MailSetting::current();
    }

    /** Why a document for this customer can't be emailed now, or null when it can. */
    public function problem(?Customer $customer): ?string
    {
        if (! $this->settings()->ready()) {
            return 'Email is not set up yet (Settings → Email).';
        }
        if (! $customer) {
            return 'It is not linked to a customer record, so there is no address to send it to.';
        }
        if (! $customer->emails()) {
            return "{$customer->name} has no email address — add one on the customer's page.";
        }

        return null;
    }

    public function bandwidthInvoice(BandwidthInvoice $invoice, ?int $userId = null): SentMail
    {
        $invoice->loadMissing(['customer', 'lines', 'payments']);
        $customer = $invoice->customer;
        if ($problem = $this->problem($customer)) {
            return $this->refused($invoice, 'bandwidth_invoice', $customer, "Invoice {$invoice->invoice_no}", $problem, $userId);
        }
        $f = $invoice->figures();
        $due = (float) $f['due'];

        $rows = [
            'Invoice No' => $invoice->invoice_no,
            'Invoice date' => $invoice->invoice_date->format('d M Y'),
            'Bill for' => $invoice->month->format('F Y'),
            'Total Bill' => $this->taka($f['bill']),
        ];
        if ((float) $f['previous'] != 0) {
            $rows[(float) $f['previous'] > 0 ? 'Previous due' : 'Advance from before'] = $this->taka(abs((float) $f['previous']));
        }
        if ($invoice->payments->isNotEmpty()) {
            $rows['Total MRC'] = $this->taka($f['mrc']);
            $rows['Paid'] = $this->taka($f['paid']);
        }
        $rows[$due < 0 ? 'Advance' : 'Amount due'] = $this->taka(abs($due));

        $mail = new DocumentMail(
            title: "Invoice {$invoice->invoice_no} — Bill for {$invoice->month->format('F Y')} · Sunlit Network DC",
            greeting: 'Dear ' . ($customer->contact_person ?: $customer->name) . ',',
            intro: "Please find your bandwidth bill for {$invoice->month->format('F Y')} from Sunlit Network DC.",
            rows: $rows,
            pdf: $this->pdf->render('pdf.bandwidth-invoice', ['invoices' => collect([$invoice])]),
            fileName: PdfMaker::fileName($invoice->invoice_no, $customer->name),
            note: $invoice->due_date && $due > 0 ? 'Please pay your bill by ' . $invoice->due_date->format('j F Y') . '.' : null,
        );

        return $this->deliver($invoice, 'bandwidth_invoice', $customer, $mail, $userId);
    }

    public function receipt(BandwidthPayment $payment, ?int $userId = null): SentMail
    {
        $invoice = $payment->invoice->loadMissing(['customer', 'lines', 'payments']);
        $customer = $invoice->customer;
        if ($problem = $this->problem($customer)) {
            return $this->refused($payment, 'receipt', $customer, "Money Receipt {$payment->receiptNo()}", $problem, $userId);
        }
        $dueAfter = $invoice->dueAfter($payment);

        $rows = [
            'Receipt No' => $payment->receiptNo(),
            'Date' => $payment->paid_on->format('d M Y'),
            'Payment method' => $payment->method . ($payment->reference ? " · Ref {$payment->reference}" : ''),
            'Against invoice' => "{$invoice->invoice_no} (Bill for {$invoice->month->format('F Y')})",
            ((float) $dueAfter < 0 ? 'Advance after this payment' : 'Due after this payment') => $this->taka(abs((float) $dueAfter)),
            'Amount received' => $this->taka($payment->amount),
        ];

        $mail = new DocumentMail(
            title: "Money Receipt {$payment->receiptNo()} — " . $this->taka($payment->amount) . ' received · Sunlit Network DC',
            greeting: 'Dear ' . ($customer->contact_person ?: $customer->name) . ',',
            intro: 'Thank you for your payment of ' . $this->taka($payment->amount) . " on {$payment->paid_on->format('d M Y')}. Your money receipt is below.",
            rows: $rows,
            pdf: $this->pdf->render('pdf.money-receipt', ['invoice' => $invoice, 'payment' => $payment, 'dueAfter' => $dueAfter]),
            fileName: PdfMaker::fileName($payment->receiptNo(), $customer->name),
        );

        return $this->deliver($payment, 'receipt', $customer, $mail, $userId);
    }

    public function settlementInvoice(ZoneSettlementRow $row, ?int $userId = null): SentMail
    {
        $row->loadMissing(['customer', 'settlement']);
        if ($problem = $this->problem($row->customer)) {
            return $this->refused($row, 'settlement_invoice', $row->customer, "Invoice {$row->invoice_no}", $problem, $userId);
        }
        $settlement = $row->settlement;
        $c = $row->calc($settlement->bkash_percent);

        $mail = new DocumentMail(
            title: "Invoice {$row->invoice_no} — Bill for {$settlement->month->format('M Y')} · Sunlit Network DC",
            greeting: 'Dear ' . ($row->customer->contact_person ?: $row->customer->name) . ',',
            intro: "Please find your invoice for {$settlement->month->format('F Y')} (billing period {$settlement->periodLabel('d M Y')}) from Sunlit Network DC.",
            rows: [
                'Invoice No' => $row->invoice_no,
                'Invoice date' => $settlement->invoice_date->format('d M Y'),
                'Total Payment' => $this->taka($c['payment']),
                'Net Bill' => $this->taka($c['difference']),
                'Total Payable' => $this->taka($c['invoice']),
            ],
            pdf: $this->pdf->render('pdf.settlement-invoice', ['settlement' => $settlement, 'rows' => collect([$row])]),
            fileName: PdfMaker::fileName($row->invoice_no, $row->displayName()),
        );

        return $this->deliver($row, 'settlement_invoice', $row->customer, $mail, $userId);
    }

    /** A plain mail to check the settings work. */
    public function test(string $to, ?int $userId = null): SentMail
    {
        $settings = $this->settings();
        $log = SentMail::create(['kind' => 'test', 'to' => $to, 'subject' => 'Test mail — Sunlit Network DC', 'user_id' => $userId]);

        try {
            $this->mailer()->raw(
                "This is a test mail from the Sunlit Network DC panel.\n\nInvoices and money receipts will be emailed to clients from {$settings->from_address}.\n\n" . now()->format('d M Y, h:i A'),
                fn ($m) => $m->to($to)->from($settings->from_address, $settings->from_name ?: 'Sunlit Network DC')->subject($log->subject)
            );
            $log->update(['status' => 'sent']);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => $this->errorText($e)]);
        }

        return $log;
    }

    /**
     * Email several documents after the page has been answered, so nobody
     * waits on the mail server — the ones whose customer has an address.
     *
     * @param  callable(Model): ?Customer  $customerOf
     * @param  callable(Model): SentMail  $send
     * @return array{note: ?string, error: ?string} what to tell the user
     */
    public function later(iterable $documents, callable $customerOf, callable $send): array
    {
        if (! $this->settings()->ready()) {
            return ['note' => null, 'error' => 'Not emailed: ' . $this->problem(null)];
        }

        [$ok, $skip] = collect($documents)->partition(fn ($d) => ! $this->problem($customerOf($d)));
        if ($ok->isNotEmpty()) {
            defer(fn () => $this->many($ok, $send));
        }

        $label = fn ($d) => trim($d->invoice_no . ' (' . ($customerOf($d)?->name ?? (method_exists($d, 'displayName') ? $d->displayName() : '—')) . ')');
        $names = $skip->take(5)->map($label)->implode(', ') . ($skip->count() > 5 ? ' and ' . ($skip->count() - 5) . ' more' : '');

        return [
            'note' => $ok->isEmpty() ? null : "Emailing {$ok->count()} " . Str::plural('invoice', $ok->count()) . " to the clients now — refresh in a minute to see each one's status.",
            'error' => $skip->isEmpty() ? null : "{$skip->count()} not emailed — no email address on the customer: {$names}.",
        ];
    }

    /**
     * Send several documents one after another (e.g. a whole settlement),
     * giving up when the mail server keeps failing so a bad password
     * doesn't time out on every one.
     *
     * @param  iterable<Model>  $documents
     * @param  callable(Model): SentMail  $send
     * @return array{sent: int, failed: int, stopped: bool}
     */
    public function many(iterable $documents, callable $send): array
    {
        @set_time_limit(0);
        $result = ['sent' => 0, 'failed' => 0, 'stopped' => false];
        $failedInARow = 0;

        foreach ($documents as $document) {
            $log = $send($document);
            $log->sent() ? $result['sent']++ : $result['failed']++;
            $failedInARow = $log->sent() ? 0 : $failedInARow + 1;

            if ($failedInARow >= 3) {
                $result['stopped'] = true;
                break;
            }
        }

        return $result;
    }

    protected function deliver(Model $document, string $kind, Customer $customer, DocumentMail $mail, ?int $userId): SentMail
    {
        $settings = $this->settings();
        $to = $customer->emails();

        $log = SentMail::create([
            'document_type' => $document::class,
            'document_id' => $document->getKey(),
            'kind' => $kind,
            'to' => implode(', ', $to),
            'subject' => Str::limit($mail->title, 250, '…'),
            'user_id' => $userId,
        ]);

        try {
            $mail->sender($settings->from_address, $settings->from_name, $settings->reply_to);
            $pending = $this->mailer()->to($to);
            if ($bcc = array_diff($settings->bccList(), $to)) {
                $pending->bcc($bcc);
            }
            $pending->send($mail);
            $log->update(['status' => 'sent']);
            ActivityLog::record('emailed', SentMail::KINDS[$kind] . " {$this->label($document)} emailed to {$log->to}", $document, null, $userId);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => $this->errorText($e)]);
            report($e);
        }

        return $log;
    }

    /** Not sent — kept on the document so its page says why. */
    protected function refused(Model $document, string $kind, ?Customer $customer, string $subject, string $problem, ?int $userId): SentMail
    {
        return SentMail::create([
            'document_type' => $document::class,
            'document_id' => $document->getKey(),
            'kind' => $kind,
            'to' => $customer ? implode(', ', $customer->emails()) : '',
            'subject' => $subject,
            'status' => 'failed',
            'error' => $problem,
            'user_id' => $userId,
        ]);
    }

    /** The office's SMTP account, built on the spot from the saved settings. */
    protected function mailer(): Mailer
    {
        return Mail::build($this->settings()->mailerConfig());
    }

    protected function label(Model $document): string
    {
        return match (true) {
            $document instanceof BandwidthPayment => $document->receiptNo(),
            default => (string) $document->invoice_no,
        };
    }

    /** The mail server's answer, short enough to show on the page. */
    protected function errorText(\Throwable $e): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $e->getMessage()));
        if (str_contains($text, 'Failed to authenticate') || str_contains($text, '535')) {
            // Symfony repeats the answer once per login method tried; the server's own words once is enough.
            preg_match('/message "(5\d\d[^"]*)"/', $text, $m);
            $text = 'The mail server refused the username or password' . (isset($m[1]) ? " ({$m[1]})." : '.');
        } elseif (str_contains($text, 'Connection could not be established') || str_contains($text, 'Connection refused') || str_contains($text, 'timed out')) {
            $text = 'Could not reach the mail server — check the host, port and encryption. ' . $text;
        }

        return Str::limit($text, 480, '…');
    }

    protected function taka($amount): string
    {
        return '৳ ' . number_format((float) Dec::round($amount, 2), 2);
    }
}
