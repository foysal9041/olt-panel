<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\ProfitSheet;
use App\Models\ZoneSettlement;
use App\Models\ZoneSettlementRow;
use App\Services\SettlementExport;
use App\Services\SettlementImporter;
use App\Support\Dec;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Zone settlement from Excel: upload each zone's Total Payment and
 * (negative) Deduction, check it, then save it with an invoice per zone.
 *
 *   Customer Invoice   = Total Payment + Deduction
 *   Payment Difference = Total Payment − Customer Invoice
 *   bKash Charge       = Total Payment × bKash %  (1.5% by default)
 *   Company Income     = Payment Difference − bKash Charge
 */
class ZoneSettlementController extends Controller
{
    public const INVOICE_PREFIX = 'Sunlit/DC/';

    public function __construct(private SettlementImporter $importer)
    {
    }

    public function index()
    {
        $settlements = ZoneSettlement::with(['rows', 'creator:id,name'])->latest('month')->latest('id')->get();

        return view('accounts.settlements.index', compact('settlements'));
    }

    /**
     * Keep the uploaded file aside and go to the preview.
     */
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:20480|extensions:xlsx,xls,csv',
            'month' => 'nullable|date_format:Y-m',
            'cycle' => 'nullable|in:' . implode(',', array_keys(ZoneSettlement::CYCLES)),
            'invoice_date' => 'nullable|date',
            'bkash_percent' => 'required|numeric|min:0|max:10',
            'blank_as_zero' => 'nullable|boolean',
        ], ['file.extensions' => 'Upload an Excel file (.xlsx or .xls) or a .csv.', 'file.max' => 'The file is larger than 20 MB.', 'file.uploaded' => 'The file did not upload — it may be larger than the server allows (20 MB).']);

        $file = $request->file('file');
        $token = (string) Str::uuid();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $path = $file->storeAs('settlements/uploads', "{$token}.{$ext}");

        try {
            // A ".xlsx" that is really text would otherwise be read as CSV.
            $type = \PhpOffice\PhpSpreadsheet\IOFactory::identify(Storage::path($path));
            if (in_array($ext, ['xlsx', 'xls'], true) && ! in_array($type, ['Xlsx', 'Xls', 'Ods', 'Xml'], true)) {
                throw new \RuntimeException("it is not a real Excel workbook (looks like {$type}). Open it in Excel and use Save As → Excel Workbook (.xlsx).");
            }
            $this->importer->sheets(Storage::path($path));
        } catch (\Throwable $e) {
            Storage::delete($path);

            return back()->withInput()->withErrors(['file' => 'Could not read this file as a spreadsheet: ' . Str::limit($e->getMessage(), 120)]);
        }

        session()->put("settlement_upload.{$token}", [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'month' => $validated['month'] ?? null,
            'cycle' => $validated['cycle'] ?? $this->guessCycle($file->getClientOriginalName()),
            'invoice_date' => $validated['invoice_date'] ?? now()->toDateString(),
            'bkash_percent' => (string) $validated['bkash_percent'],
            'blank_as_zero' => $request->boolean('blank_as_zero'),
        ]);

        return redirect()->route('accounts.settlements.preview', ['token' => $token]);
    }

    /**
     * What will be saved: detected columns (changeable), every row with its
     * calculation and flags, totals and the check against the sheet's Total row.
     */
    public function preview(Request $request)
    {
        [$token, $upload] = $this->upload_($request);
        $state = $this->state($request, $upload);

        return view('accounts.settlements.preview', $state + ['token' => $token, 'upload' => $upload] + $this->duplicates($state, $upload));
    }

    public function store(Request $request)
    {
        [$token, $upload] = $this->upload_($request);
        $state = $this->state($request, $upload);

        $request->validate([
            'month' => 'required|date_format:Y-m',
            'cycle' => 'required|in:' . implode(',', array_keys(ZoneSettlement::CYCLES)),
            'invoice_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ]);

        $month = Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth();
        if (ProfitSheet::isMonthClosed($month)) {
            return back()->with('error', "{$month->format('F Y')} is closed (Net Profit finalized). An admin has to reopen that month before adding a settlement to it.");
        }

        if (! $state['map']['name'] || ! $state['map']['payment'] || ! $state['map']['deduction']) {
            return back()->with('error', 'Choose the name, Total Payment and Deduction columns first.');
        }
        if (! collect($state['result']['rows'])->where('included', true)->count()) {
            return back()->with('error', 'No row can be counted — check the columns and the flags below.');
        }

        // The same file, or a zone already settled for this month and group, would count twice.
        $dupes = $this->duplicates(['month' => $month->format('Y-m'), 'cycle' => $request->input('cycle')] + $state, $upload);
        if ($dupes['sameFile']) {
            return back()->with('error', "This exact file is already saved as the {$dupes['sameFile']->month->format('F Y')} {$dupes['sameFile']->cycleLabel()} settlement ({$dupes['sameFile']->source_name}). Not saved again.");
        }
        if ($dupes['overlap']) {
            return back()->with('error', 'Not saved — these zones are already in a ' . $month->format('F Y') . ' ' . (ZoneSettlement::CYCLES[$request->input('cycle')]['label'] ?? '') . ' settlement: '
                . collect($dupes['overlap'])->map(fn ($o) => "{$o['name']} ({$o['in']})")->implode(', ') . '. Take them out of the Excel, or delete that settlement first.');
        }

        $settlement = DB::transaction(function () use ($state, $upload, $request) {
            $settlement = ZoneSettlement::create([
                'month' => Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth(),
                'cycle' => $request->input('cycle'),
                'invoice_date' => $request->input('invoice_date'),
                'bkash_percent' => $state['bkash_percent'],
                'source_name' => $upload['name'],
                'sheet_name' => $state['sheet'],
                'mapping' => $state['map'],
                'original' => $state['rows'],
                'blank_deduction_as_zero' => $state['blank_as_zero'],
                'notes' => $request->input('notes'),
                'created_by' => auth()->id(),
            ] + (($prev = ZoneSettlement::latest('id')->first()) ? ['prepared_by' => $prev->prepared_by, 'prepared_title' => $prev->prepared_title] : []));

            // Next number after the highest ever given; locked so two saves can't share one.
            $next = $this->lastInvoiceNumber() + 1;

            foreach ($state['result']['rows'] as $row) {
                $settlement->rows()->create([
                    'source_row' => $row['source_row'],
                    'name' => $row['name'] ?: null,
                    'customer_id' => $row['customer_id'],
                    'total_payment' => $row['total_payment'],
                    'deduction' => $row['deduction'],
                    'included' => $row['included'],
                    'flags' => array_values(array_map(fn ($f) => ['level' => $f[0], 'text' => $f[1]], $row['flags'])) ?: null,
                    'invoice_no' => $row['included'] ? self::INVOICE_PREFIX . str_pad((string) $next++, 3, '0', STR_PAD_LEFT) : null,
                ]);
            }

            // Keep the source file with the settlement.
            $ext = pathinfo($upload['path'], PATHINFO_EXTENSION);
            $final = "settlements/{$settlement->id}/source.{$ext}";
            Storage::move($upload['path'], $final);
            $settlement->update(['source_path' => $final, 'file_hash' => hash_file('sha256', Storage::path($final))]);

            return $settlement;
        });

        session()->forget("settlement_upload.{$token}");

        $count = $settlement->rows()->where('included', true)->count();

        return redirect()->route('accounts.settlements.show', $settlement)
            ->with('success', "Saved {$settlement->month->format('F Y')} · {$settlement->cycleLabel()}: {$count} " . Str::plural('zone', $count) . ' invoiced.');
    }

    public function show(ZoneSettlement $settlement)
    {
        $settlement->load('rows.customer:id,name', 'creator:id,name');

        return view('accounts.settlements.show', [
            'settlement' => $settlement,
            'totals' => $settlement->totals(),
            'reconcile' => $this->reconcileSaved($settlement),
        ]);
    }

    public function invoice(ZoneSettlement $settlement, ZoneSettlementRow $row)
    {
        abort_unless($row->zone_settlement_id === $settlement->id && $row->included, 404);

        return view('accounts.settlements.invoice', ['settlement' => $settlement, 'rows' => collect([$row->load('customer')])]);
    }

    public function invoices(ZoneSettlement $settlement)
    {
        return view('accounts.settlements.invoice', [
            'settlement' => $settlement,
            'rows' => $settlement->rows()->where('included', true)->with('customer')->get(),
        ]);
    }

    public function export(ZoneSettlement $settlement, SettlementExport $export)
    {
        $settlement->load('rows');
        $file = $export->build($settlement);

        return response()->download($file, 'Zone Settlement ' . $settlement->month->format('Y-m') . '.xlsx')->deleteFileAfterSend();
    }

    public function source(ZoneSettlement $settlement)
    {
        abort_unless($settlement->source_path && Storage::exists($settlement->source_path), 404);

        return Storage::download($settlement->source_path, $settlement->source_name);
    }

    /**
     * "Prepared By" name and title printed on the customer invoices.
     */
    public function signatory(Request $request, ZoneSettlement $settlement)
    {
        $settlement->update($request->validate([
            'prepared_by' => 'required|string|max:100',
            'prepared_title' => 'nullable|string|max:100',
        ]));

        return back()->with('success', 'Invoices will be signed by ' . $settlement->prepared_by . '.');
    }

    public function destroy(ZoneSettlement $settlement)
    {
        if ($settlement->isClosed()) {
            return back()->with('error', "{$settlement->month->format('F Y')} is closed (Net Profit finalized). An admin has to reopen that month first.");
        }

        $label = $settlement->month->format('F Y');
        DB::transaction(function () use ($settlement) {
            if ($settlement->source_path) {
                Storage::deleteDirectory(dirname($settlement->source_path));
            }
            $settlement->delete();
        });

        return redirect()->route('accounts.settlements.index')->with('success', "Settlement for {$label} deleted.");
    }

    // ---- helpers -------------------------------------------------------------

    /**
     * Would saving this count something twice? The same file saved before,
     * or zones (by customer, else by name) already in another settlement of
     * the same month and group.
     *
     * @return array{sameFile: ?ZoneSettlement, overlap: list<array{name: string, in: string}>}
     */
    private function duplicates(array $state, array $upload): array
    {
        $hash = is_file(Storage::path($upload['path'])) ? hash_file('sha256', Storage::path($upload['path'])) : null;
        $sameFile = $hash ? ZoneSettlement::where('file_hash', $hash)->first() : null;

        $overlap = [];
        if (! empty($state['month']) && ! empty($state['cycle'])) {
            $others = ZoneSettlement::with('rows')->whereDate('month', Carbon::createFromFormat('Y-m', $state['month'])->startOfMonth())
                ->where('cycle', $state['cycle'])->get();
            $seen = [];
            foreach ($others as $o) {
                foreach ($o->rows->where('included', true) as $r) {
                    $key = $r->customer_id ? 'c' . $r->customer_id : 'n' . $this->importer->norm((string) $r->name);
                    $seen[$key] = $o->source_name;
                }
            }
            foreach (collect($state['result']['rows'] ?? [])->where('included', true) as $row) {
                $key = $row['customer_id'] ? 'c' . $row['customer_id'] : 'n' . $this->importer->norm((string) $row['name']);
                if (isset($seen[$key])) {
                    $overlap[] = ['name' => (string) $row['name'], 'in' => $seen[$key]];
                }
            }
        }

        return ['sameFile' => $sameFile, 'overlap' => $overlap];
    }

    /** NTTN-SEP.xlsx → nttn, "Balunda …" → balunda, else the fixed-date cycle. */
    private function guessCycle(string $fileName): string
    {
        $n = strtolower($fileName);

        return match (true) {
            str_contains($n, 'nttn') => 'nttn',
            str_contains($n, 'balun') => 'balunda',
            default => 'fixed',
        };
    }

    /**
     * @return array{0: string, 1: array}
     */
    private function upload_(Request $request): array
    {
        $token = (string) $request->input('token', $request->query('token'));
        $upload = session("settlement_upload.{$token}");
        abort_unless($upload && Storage::exists($upload['path']), 410, 'This upload has expired — please upload the file again.');

        return [$token, $upload];
    }

    /**
     * Sheet, columns and settings (from the form, else detected) and the
     * resulting analysis.
     */
    private function state(Request $request, array $upload): array
    {
        $sheets = $this->importer->sheets(Storage::path($upload['path']));
        $sheet = array_key_exists((string) $request->input('sheet'), $sheets) ? (string) $request->input('sheet') : $this->importer->bestSheet($sheets);
        $rows = $sheets[$sheet] ?? [];
        $detected = $this->importer->detect($rows);

        $cols = collect($rows)->flatMap(fn ($cells) => array_keys($cells))->unique()->sort()->values()->all();
        $pickCol = fn ($key) => in_array($request->input($key), $cols, true) ? $request->input($key) : ($request->has($key) && $request->input($key) === '' ? null : $detected[$key]);
        $headerRow = $request->filled('header_row') ? max(0, (int) $request->input('header_row')) : ($detected['header_row'] ?? 0);

        $map = ['header_row' => $headerRow ?: null, 'name' => $pickCol('name'), 'payment' => $pickCol('payment'), 'deduction' => $pickCol('deduction')];
        $bkash = is_numeric($request->input('bkash_percent')) ? (string) $request->input('bkash_percent') : $upload['bkash_percent'];
        $blankAsZero = $request->has('blank_as_zero') ? $request->boolean('blank_as_zero') : $upload['blank_as_zero'];

        $result = ($map['name'] && $map['payment'] && $map['deduction'])
            ? $this->importer->analyse($rows, $map, $blankAsZero, $bkash)
            : ['rows' => [], 'reconcile' => []];

        $totals = ['count' => 0, 'payment' => '0', 'deduction' => '0', 'invoice' => '0', 'difference' => '0', 'bkash' => '0', 'income' => '0'];
        foreach ($result['rows'] as $r) {
            if ($r['included']) {
                $totals['count']++;
                foreach (['payment', 'deduction', 'invoice', 'difference', 'bkash', 'income'] as $k) {
                    $totals[$k] = Dec::add($totals[$k], $r['calc'][$k]);
                }
            }
        }

        $month = $request->input('month') ?: $upload['month'] ?: $this->importer->detectMonth($rows, $sheet, $upload['name']);

        return [
            'sheets' => array_map('count', $sheets),
            'sheet' => $sheet,
            'rows' => $rows,
            'cols' => $cols,
            'headers' => $map['header_row'] ? ($rows[$map['header_row']] ?? []) : [],
            'map' => $map,
            'detected' => $detected,
            'bkash_percent' => $bkash,
            'blank_as_zero' => $blankAsZero,
            'month' => $month,
            'cycle' => $request->input('cycle') ?: ($upload['cycle'] ?? 'fixed'),
            'invoice_date' => $request->input('invoice_date') ?: $upload['invoice_date'],
            'result' => $result,
            'totals' => $totals,
            'nextInvoice' => $this->lastInvoiceNumber() + 1,
        ];
    }

    private function lastInvoiceNumber(): int
    {
        return (int) ZoneSettlementRow::where('invoice_no', 'like', self::INVOICE_PREFIX . '%')->lockForUpdate()->pluck('invoice_no')
            ->map(fn ($no) => (int) substr($no, strlen(self::INVOICE_PREFIX)))->max();
    }

    /**
     * The check against the sheet's own Total row, for a saved settlement.
     */
    private function reconcileSaved(ZoneSettlement $s): array
    {
        $totalRow = $s->rows->first(fn ($r) => ! $r->included && str_starts_with($r->flags[0]['text'] ?? '', 'Total row'));
        if (! $totalRow) {
            return [];
        }

        $counted = $s->rows->where('included', true);
        $checks = [];
        foreach ([['Total Payment', $totalRow->total_payment, Dec::add('0', ...$counted->pluck('total_payment'))],
                  ['Deduction', $totalRow->deduction, Dec::add('0', ...$counted->pluck('deduction'))]] as [$label, $sheet, $ours]) {
            if ($sheet !== null) {
                $checks[] = ['label' => $label, 'sheet' => $sheet, 'ours' => $ours, 'ok' => Dec::round($sheet) === Dec::round($ours)];
            }
        }

        return $checks;
    }
}
