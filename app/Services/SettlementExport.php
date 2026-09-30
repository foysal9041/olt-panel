<?php

namespace App\Services;

use App\Models\ZoneSettlement;
use App\Support\Dec;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The settlement as an Excel workbook:
 *   1. Original Data        — the uploaded sheet as it was read
 *   2. Customer Calculation — every counted zone, derived columns as formulas
 *   3. Grand Summary        — totals (linked to sheet 2)
 *   4. Invoice Summary      — one line per invoice (linked to sheet 2)
 *   5. Checks               — rows not counted or needing a look (if any)
 */
class SettlementExport
{
    protected const MONEY = '"৳"#,##0.00;[Red]-"৳"#,##0.00';
    protected const NAVY = '1E3A8A';

    public function build(ZoneSettlement $s): string
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('Sunlit Network DC')->setTitle('Zone Settlement ' . $s->month->format('F Y'));

        $this->original($book->getActiveSheet(), $s);
        [$calc, $first, $last, $totalRow] = $this->calculation($book->createSheet(), $s);
        $this->summary($book->createSheet(), $s, $totalRow);
        $this->invoices($book->createSheet(), $s, $first, $last);
        $this->checks($book, $s);

        $book->setActiveSheetIndex(1);
        $path = tempnam(sys_get_temp_dir(), 'settle') . '.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    protected function original(Worksheet $ws, ZoneSettlement $s): void
    {
        $ws->setTitle('Original Data');
        foreach (($s->original ?? []) as $r => $cells) {
            foreach ($cells as $col => $v) {
                if ($v !== null && $v !== '') {
                    $ws->setCellValueExplicit($col . $r, $v, is_numeric($v) && ! is_string($v)
                        ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                        : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }
        $ws->setCellValue('A' . (max(array_keys($s->original ?: [1 => []])) + 2),
            "Copy of \"{$s->source_name}\" (sheet \"{$s->sheet_name}\") as uploaded. Nothing here has been changed.");
    }

    /**
     * @return array{0: Worksheet, 1: int, 2: int, 3: int}  sheet, first/last data row, total row
     */
    protected function calculation(Worksheet $ws, ZoneSettlement $s): array
    {
        $ws->setTitle('Customer Calculation');
        $rate = Dec::div($s->bkash_percent, 100);
        $this->title($ws, 'Customer-wise Calculation — ' . $s->month->format('F Y'), 'J');

        $head = ['SN', 'Customer/Zone', 'Total Payment', 'Deduction', 'Total Payable (Customer Invoice)', 'Payment Difference',
            'Bkash Charge (' . rtrim(rtrim($s->bkash_percent, '0'), '.') . '%)', 'Net Bill (Company Income)', 'Invoice No', 'Excel Row'];
        $ws->fromArray($head, null, 'A3');
        $this->header($ws, 'A3:J3');
        $ws->setCellValue('L3', 'bKash rate');
        $ws->setCellValue('M3', (float) $rate);
        $ws->getStyle('M3')->getNumberFormat()->setFormatCode('0.00%');

        $r = 4;
        $sn = 1;
        $s->loadMissing('rows.customer');
        foreach ($s->rows->where('included', true) as $row) {
            $ws->setCellValue("A{$r}", $sn++);
            $ws->setCellValue("B{$r}", $row->displayName() . ($row->username() ? " ({$row->username()})" : ''));
            $ws->setCellValue("C{$r}", (float) $row->total_payment);
            $ws->setCellValue("D{$r}", (float) $row->deduction);
            $ws->setCellValue("E{$r}", "=C{$r}+D{$r}");            // Customer Invoice = Total Payment + Deduction
            $ws->setCellValue("F{$r}", "=C{$r}-E{$r}");            // Payment Difference
            $ws->setCellValue("G{$r}", "=C{$r}*\$M\$3");           // bKash Charge
            $ws->setCellValue("H{$r}", "=F{$r}-G{$r}");            // Company Income
            $ws->setCellValue("I{$r}", $row->invoice_no);
            $ws->setCellValue("J{$r}", $row->source_row);
            $r++;
        }
        $first = 4;
        $last = $r - 1;

        $ws->setCellValue("B{$r}", 'Grand Total');
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $c) {
            $ws->setCellValue("{$c}{$r}", "=SUM({$c}{$first}:{$c}{$last})");
        }
        $ws->getStyle("A{$r}:J{$r}")->getFont()->setBold(true);
        $ws->getStyle("A{$r}:J{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');

        $ws->getStyle("C{$first}:H{$r}")->getNumberFormat()->setFormatCode(self::MONEY);
        $this->grid($ws, "A3:J{$r}");
        $ws->freezePane('C4');
        $this->widths($ws, ['A' => 6, 'B' => 30, 'C' => 16, 'D' => 16, 'E' => 17, 'F' => 18, 'G' => 17, 'H' => 17, 'I' => 16, 'J' => 10, 'L' => 11, 'M' => 9]);

        $ws->setCellValue('B' . ($r + 2), 'Customer Invoice = Total Payment + Deduction (Deduction is already negative)');
        $ws->setCellValue('B' . ($r + 3), 'Payment Difference = Total Payment − Customer Invoice');
        $ws->setCellValue('B' . ($r + 4), 'bKash Charge = Total Payment × bKash rate');
        $ws->setCellValue('B' . ($r + 5), 'Company Income = Payment Difference − bKash Charge');
        $ws->getStyle('B' . ($r + 2) . ':B' . ($r + 5))->getFont()->setItalic(true)->getColor()->setRGB('475569');

        return [$ws, $first, $last, $r];
    }

    protected function summary(Worksheet $ws, ZoneSettlement $s, int $t): void
    {
        $ws->setTitle('Grand Summary');
        $this->title($ws, 'Grand Summary — ' . $s->month->format('F Y'), 'B');
        $cc = "'Customer Calculation'!";
        $lines = [
            ['Billing month', $s->month->format('F Y')],
            ['Invoice date', $s->invoice_date->format('d M Y')],
            ['Total Customers/Zones', "=COUNT({$cc}A4:A" . ($t - 1) . ')'],
            ['Total Payment', "={$cc}C{$t}"],
            ['Total Deduction', "={$cc}D{$t}"],
            ['Total Payable (Customer Invoice)', "={$cc}E{$t}"],
            ['Total Payment Difference', "={$cc}F{$t}"],
            ['Total bKash Charge (' . rtrim(rtrim($s->bkash_percent, '0'), '.') . '%)', "={$cc}G{$t}"],
            ['Net Bill total (Company Income)', "={$cc}H{$t}"],
        ];
        $ws->fromArray($lines, null, 'A3');
        $ws->getStyle('A3:A11')->getFont()->setBold(true);
        $ws->getStyle('B6:B11')->getNumberFormat()->setFormatCode(self::MONEY);
        $ws->getStyle('B3:B11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getStyle('A11:B11')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
        $this->grid($ws, 'A3:B11');
        $this->widths($ws, ['A' => 32, 'B' => 22]);
    }

    protected function invoices(Worksheet $ws, ZoneSettlement $s, int $first, int $last): void
    {
        $ws->setTitle('Invoice Summary');
        $this->title($ws, 'Invoice Summary — ' . $s->month->format('F Y'), 'H');
        $ws->fromArray(['Invoice No', 'Date', 'Customer/Zone', 'Total Payment', 'Total Payable', 'Payment Difference', 'Bkash Charge', 'Net Bill'], null, 'A3');
        $this->header($ws, 'A3:H3');

        $cc = "'Customer Calculation'!";
        $r = 4;
        for ($src = $first; $src <= $last; $src++, $r++) {
            $ws->setCellValue("A{$r}", "={$cc}I{$src}");
            $ws->setCellValue("B{$r}", $s->invoice_date->format('d/m/Y'));
            $ws->setCellValue("C{$r}", "={$cc}B{$src}");
            foreach (['D' => 'C', 'E' => 'E', 'F' => 'F', 'G' => 'G', 'H' => 'H'] as $to => $from) {
                $ws->setCellValue("{$to}{$r}", "={$cc}{$from}{$src}");
            }
        }
        if ($r > 4) {
            $ws->getStyle('D4:H' . ($r - 1))->getNumberFormat()->setFormatCode(self::MONEY);
            $this->grid($ws, 'A3:H' . ($r - 1));
        }
        $ws->freezePane('A4');
        $this->widths($ws, ['A' => 16, 'B' => 13, 'C' => 30, 'D' => 16, 'E' => 17, 'F' => 18, 'G' => 15, 'H' => 17]);
    }

    protected function checks(Spreadsheet $book, ZoneSettlement $s): void
    {
        $flagged = $s->rows->filter(fn ($r) => ! $r->included || ! empty($r->flags));
        if ($flagged->isEmpty()) {
            return;
        }

        $ws = $book->createSheet();
        $ws->setTitle('Checks');
        $this->title($ws, 'Rows to check', 'F');
        $ws->fromArray(['Excel Row', 'Customer/Zone', 'Total Payment', 'Deduction', 'Counted', 'Notes'], null, 'A3');
        $this->header($ws, 'A3:F3');
        $r = 4;
        foreach ($flagged as $row) {
            $ws->fromArray([
                $row->source_row, $row->name, $row->total_payment !== null ? (float) $row->total_payment : null,
                $row->deduction !== null ? (float) $row->deduction : null, $row->included ? 'Yes' : 'No',
                collect($row->flags ?? [])->pluck('text')->implode('; '),
            ], null, "A{$r}");
            $r++;
        }
        $ws->getStyle('C4:D' . ($r - 1))->getNumberFormat()->setFormatCode(self::MONEY);
        $this->grid($ws, 'A3:F' . ($r - 1));
        $this->widths($ws, ['A' => 10, 'B' => 28, 'C' => 16, 'D' => 16, 'E' => 9, 'F' => 70]);
    }

    // ---- styling helpers --------------------------------------------------

    protected function title(Worksheet $ws, string $text, string $lastCol): void
    {
        $ws->setCellValue('A1', 'Sunlit Network DC — ' . $text);
        $ws->mergeCells("A1:{$lastCol}1");
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB(self::NAVY);
    }

    protected function header(Worksheet $ws, string $range): void
    {
        $style = $ws->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }

    protected function grid(Worksheet $ws, string $range): void
    {
        $ws->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('94A3B8');
    }

    protected function widths(Worksheet $ws, array $widths): void
    {
        foreach ($widths as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
    }
}
