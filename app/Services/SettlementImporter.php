<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ZoneSettlementRow;
use App\Support\Dec;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads a zone settlement workbook and works out which columns hold the
 * zone name, Total Payment and Deduction — without changing any value.
 *
 * Every row is checked; problems are flagged, never silently fixed:
 *  - error (row not counted): no name, Total Payment blank or not a number,
 *    Deduction not a number (or blank, unless "treat blank as 0" is chosen);
 *  - warning (counted, please check): positive Deduction (used as it is),
 *    negative Total Payment, invoice below zero, same name twice;
 *  - skipped: an exact duplicate of an earlier row, and the sheet's own
 *    Total row (used only to check our totals against it).
 */
class SettlementImporter
{
    public const MAX_ROWS = 3000;
    public const MAX_COLS = 40;

    protected const NAME_WORDS = '/zone|customer|client|name|pop|partner|area|reseller|জোন|নাম|গ্রাহক|এলাকা/iu';
    protected const PAY_WORDS = '/payment|collection|collect|received|receive|paid|amount|bill|total|taka|tk|মোট|পেমেন্ট|আদায়|জমা|টাকা/iu';
    protected const DED_WORDS = '/deduct|less|discount|commission|adjust|minus|cut|share|কর্তন|বাদ|কমিশন/iu';
    protected const TOTAL_ROW = '/^\s*(grand\s*)?(total|sum)\b|^\s*মোট\s*$|^\s*সর্বমোট/iu';

    /**
     * Every sheet as rows keyed by Excel row number, cells keyed by column
     * letter — values exactly as stored (formulas calculated).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function sheets(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);

        $out = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            $rows = [];
            $maxRow = min($sheet->getHighestDataRow(), self::MAX_ROWS);
            $maxCol = min(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), self::MAX_COLS);
            if ($maxRow < 1) {
                $out[$sheet->getTitle()] = [];
                continue;
            }
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxCol);
            foreach ($sheet->rangeToArray("A1:{$lastCol}{$maxRow}", null, true, false, true) as $r => $cells) {
                if (collect($cells)->filter(fn ($v) => $v !== null && trim((string) $v) !== '')->isNotEmpty()) {
                    $rows[(int) $r] = $cells;
                }
            }
            $out[$sheet->getTitle()] = $rows;
        }

        $book->disconnectWorksheets();

        return $out;
    }

    /**
     * Header row and the three columns, from header words, falling back
     * to what the columns contain.
     *
     * @return array{header_row:?int, name:?string, payment:?string, deduction:?string, score:int}
     */
    public function detect(array $rows): array
    {
        $best = ['header_row' => null, 'name' => null, 'payment' => null, 'deduction' => null, 'score' => 0];

        foreach (array_slice($rows, 0, 25, true) as $r => $cells) {
            // Score every header cell for each role; the best column wins each role.
            $scores = [];
            foreach ($cells as $col => $v) {
                $t = is_string($v) ? trim($v) : '';
                if ($t === '' || $this->number($t) !== null) {
                    continue;
                }
                $scores['name'][$col] = $this->nameScore($t);
                $scores['payment'][$col] = $this->paymentScore($t);
                $scores['deduction'][$col] = $this->deductionScore($t);
            }

            $found = ['name' => null, 'payment' => null, 'deduction' => null];
            $total = 0;
            $taken = [];
            // Deduction and payment first (most specific), then the name.
            foreach (['deduction', 'payment', 'name'] as $role) {
                $cands = array_filter($scores[$role] ?? [], fn ($sc, $col) => $sc > 0 && ! in_array($col, $taken, true), ARRAY_FILTER_USE_BOTH);
                if ($cands) {
                    arsort($cands);
                    $col = array_key_first($cands);
                    $found[$role] = $col;
                    $taken[] = $col;
                    $total += $cands[$col];
                }
            }

            if ($total > $best['score']) {
                $best = ['header_row' => $r] + $found + ['score' => $total];
            }
        }

        $data = $best['header_row'] ? array_filter($rows, fn ($r) => $r > $best['header_row'], ARRAY_FILTER_USE_KEY) : $rows;
        $stats = $this->columnStats($data);

        $best['name'] ??= collect($stats)->sortByDesc('text')->keys()->first(fn ($c) => $stats[$c]['text'] > 0);
        $best['deduction'] ??= collect($stats)->filter(fn ($s, $c) => $c !== $best['payment'] && $s['neg'] > 0 && $s['neg'] >= $s['pos'])
            ->sortByDesc('neg')->keys()->first();
        $best['payment'] ??= collect($stats)->filter(fn ($s, $c) => $c !== $best['deduction'] && $c !== $best['name'] && $s['pos'] > 0)
            ->sortByDesc('sum')->keys()->first();

        return $best;
    }

    /**
     * How strongly a header names the Total Payment column. "Payment" /
     * "Total Payment" beat "Collection", which beats "Bill" / "Amount".
     */
    protected function paymentScore(string $t): int
    {
        return match (true) {
            (bool) preg_match('/deduct|commission|discount|return|refund|%|percent|কর্তন|কমিশন/iu', $t) => 0,
            (bool) preg_match('/^\s*(total\s*)?payments?\s*$|^\s*মোট\s*পেমেন্ট\s*$/iu', $t) => 100,
            (bool) preg_match('/payment|পেমেন্ট/iu', $t) => 80,
            (bool) preg_match('/collection|collected|received|paid|আদায়|জমা/iu', $t) => 60,
            (bool) preg_match('/^\s*total\s*$|^\s*মোট\s*$/iu', $t) => 30,
            (bool) preg_match('/bill|amount|\bamt\b|taka|\btk\b|টাকা/iu', $t) => 10,
            default => 0,
        };
    }

    /**
     * How strongly a header names the Deduction column. "Deduction" beats
     * "Less", which beats "Commission" / "Discount".
     */
    protected function deductionScore(string $t): int
    {
        return match (true) {
            (bool) preg_match('/%|percent/iu', $t) => 0,
            (bool) preg_match('/^\s*(total\s*)?deductions?\s*$|^\s*কর্তন\s*$/iu', $t) => 100,
            (bool) preg_match('/deduct|কর্তন/iu', $t) => 80,
            (bool) preg_match('/\bless\b|minus|\bcut\b|বাদ/iu', $t) => 40,
            (bool) preg_match('/commission|discount|adjust|share|কমিশন/iu', $t) => 20,
            default => 0,
        };
    }

    protected function nameScore(string $t): int
    {
        return match (true) {
            (bool) preg_match('/^\s*(customer|client|zone|pop|partner|reseller)?\s*(name)?\s*$/iu', $t) && trim($t) !== '' => 100,
            (bool) preg_match('/zone|customer|client|name|pop|partner|reseller|জোন|নাম|গ্রাহক/iu', $t) => 60,
            (bool) preg_match('/area|এলাকা/iu', $t) => 30,
            default => 0,
        };
    }

    /**
     * The sheet the zone data is most likely on.
     */
    public function bestSheet(array $sheets): ?string
    {
        $best = null;
        $bestScore = -1;
        foreach ($sheets as $name => $rows) {
            $d = $this->detect($rows);
            $score = $d['score'] + count(array_filter([$d['name'], $d['payment'], $d['deduction']])) + min(count($rows), 50) / 100;
            if ($score > $bestScore) {
                [$best, $bestScore] = [$name, $score];
            }
        }

        return $best;
    }

    /**
     * Billing month found in the top rows, the sheet name or the file name ("2026-08").
     */
    public function detectMonth(array $rows, string $sheetName, string $fileName): ?string
    {
        $months = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
            'জানুয়ারি' => 1, 'ফেব্রুয়ারি' => 2, 'মার্চ' => 3, 'এপ্রিল' => 4, 'মে' => 5, 'জুন' => 6, 'জুলাই' => 7, 'আগস্ট' => 8, 'সেপ্টেম্বর' => 9, 'অক্টোবর' => 10, 'নভেম্বর' => 11, 'ডিসেম্বর' => 12];
        $texts = [$sheetName, $fileName];
        foreach (array_slice($rows, 0, 8, true) as $cells) {
            foreach ($cells as $v) {
                if (is_string($v)) {
                    $texts[] = $v;
                }
            }
        }

        foreach ($texts as $t) {
            $t = $this->digits(mb_strtolower($t));
            foreach ($months as $word => $m) {
                if (mb_strpos($t, $word) !== false && preg_match('/(20\d{2})/', $t, $y)) {
                    return sprintf('%04d-%02d', $y[1], $m);
                }
            }
            if (preg_match('/(20\d{2})[-\/.](0?[1-9]|1[0-2])\b/', $t, $x)) {
                return sprintf('%04d-%02d', $x[1], $x[2]);
            }
        }

        return null;
    }

    /**
     * Every data row with its values, calculation, flags and whether it counts.
     *
     * @return array{rows: list<array>, reconcile: list<array>}
     */
    public function analyse(array $rows, array $map, bool $blankAsZero, string $bkashPercent): array
    {
        $customers = $this->customerIndex();
        $out = [];
        $seen = [];
        $totalRows = [];

        foreach ($rows as $r => $cells) {
            if ($map['header_row'] && $r <= $map['header_row']) {
                continue;
            }

            $rawName = $map['name'] ? $cells[$map['name']] ?? null : null;
            $name = is_scalar($rawName) ? trim((string) $rawName) : '';
            $rawPay = $map['payment'] ? $cells[$map['payment']] ?? null : null;
            $rawDed = $map['deduction'] ? $cells[$map['deduction']] ?? null : null;
            $pay = $this->number($rawPay);
            $ded = $this->number($rawDed);
            $payBlank = $this->blank($rawPay);
            $dedBlank = $this->blank($rawDed);

            // Nothing in the columns we use: not a data row.
            if ($name === '' && $payBlank && $dedBlank) {
                continue;
            }

            $row = ['source_row' => $r, 'name' => $name, 'raw_payment' => $rawPay, 'raw_deduction' => $rawDed,
                'total_payment' => $pay, 'deduction' => $ded, 'included' => true, 'flags' => [], 'customer_id' => null, 'kind' => 'data'];

            if (preg_match(self::TOTAL_ROW, $name)) {
                $row['included'] = false;
                $row['kind'] = 'total';
                $row['flags'][] = ['info', 'Total row in the sheet — not counted; used to check our totals'];
                $totalRows[] = $row;
                $out[] = $row;
                continue;
            }

            if ($name === '') {
                $row['flags'][] = ['error', 'No customer/zone name'];
            }
            if ($payBlank) {
                $row['flags'][] = ['error', 'Total Payment is blank'];
            } elseif ($pay === null) {
                $row['flags'][] = ['error', 'Total Payment is not a number: "' . $this->show($rawPay) . '"'];
            }
            if ($dedBlank) {
                if ($blankAsZero) {
                    $row['deduction'] = '0';
                    $row['flags'][] = ['warn', 'Deduction is blank — counted as 0 (as chosen)'];
                } else {
                    $row['flags'][] = ['error', 'Deduction is blank'];
                }
            } elseif ($ded === null) {
                $row['flags'][] = ['error', 'Deduction is not a number: "' . $this->show($rawDed) . '"'];
            }

            if (collect($row['flags'])->contains(fn ($f) => $f[0] === 'error')) {
                $row['included'] = false;
            } else {
                if (Dec::isNegative($pay)) {
                    $row['flags'][] = ['warn', 'Total Payment is negative'];
                }
                if (bccomp(Dec::of($row['deduction']), '0', Dec::SCALE) > 0) {
                    $row['flags'][] = ['warn', 'Deduction is positive — used exactly as it is (not changed to negative)'];
                }
                if (Dec::isNegative(Dec::add($pay, $row['deduction']))) {
                    $row['flags'][] = ['warn', 'Customer Invoice comes out below zero'];
                }

                $key = $this->norm($name);
                $sig = $key . '|' . Dec::of($pay) . '|' . Dec::of($row['deduction']);
                if (isset($seen['sig'][$sig])) {
                    $row['included'] = false;
                    $row['kind'] = 'duplicate';
                    $row['flags'][] = ['info', "Exact duplicate of row {$seen['sig'][$sig]} — not counted twice"];
                } elseif (isset($seen['name'][$key])) {
                    $row['flags'][] = ['warn', "Same name as row {$seen['name'][$key]} (different amounts) — counted, please check"];
                }
                $seen['sig'][$sig] ??= $r;
                $seen['name'][$key] ??= $r;

                [$zone, $username] = self::splitName($name);
                $row['customer_id'] = ($username !== null ? $customers[$this->norm($username)] ?? null : null)
                    ?? $customers[$this->norm($zone)] ?? $customers[$key] ?? null;
            }

            $out[] = $row;
        }

        // Rows counted, with their calculation.
        foreach ($out as &$row) {
            $row['calc'] = $row['included'] ? ZoneSettlementRow::compute($row['total_payment'], $row['deduction'], $bkashPercent) : null;
            if ($row['calc'] && Dec::isNegative($row['calc']['income'])) {
                $row['flags'][] = ['warn', 'Company Income comes out below zero — check the Payment and Deduction for this row'];
            }
        }
        unset($row);

        return ['rows' => $out, 'reconcile' => $this->reconcile($out, $totalRows)];
    }

    /**
     * Compare the sheet's own Total row(s) with the sum of the rows we counted.
     *
     * @return list<array{label:string, sheet:string, ours:string, ok:bool}>
     */
    protected function reconcile(array $rows, array $totalRows): array
    {
        if (! $totalRows) {
            return [];
        }

        $counted = array_filter($rows, fn ($r) => $r['included']);
        $pay = Dec::add(...array_map(fn ($r) => $r['total_payment'], $counted ?: [['total_payment' => 0]]));
        $ded = Dec::add(...array_map(fn ($r) => $r['deduction'], $counted ?: [['deduction' => 0]]));
        $t = end($totalRows);
        $checks = [];

        foreach ([['Total Payment', $t['total_payment'], $pay], ['Deduction', $t['deduction'], $ded]] as [$label, $sheet, $ours]) {
            if ($sheet !== null) {
                $checks[] = ['label' => $label, 'sheet' => $sheet, 'ours' => $ours,
                    'ok' => bccomp(Dec::round($sheet, 2), Dec::round($ours, 2), 2) === 0];
            }
        }

        return $checks;
    }

    /**
     * A money cell as an exact decimal string, or null if it isn't a number.
     * Understands 81,198.02 · ৳81198 · (40,599.01) · −40599.01 · Bangla digits.
     */
    public function number($v): ?string
    {
        if (is_int($v) || is_float($v)) {
            return Dec::of($v);
        }
        if (! is_string($v)) {
            return null;
        }

        $s = $this->digits(trim($v));
        $s = str_replace(["\u{2212}", "\u{2013}", "\u{2014}"], '-', $s);
        $s = preg_replace('/(৳|tk\.?|taka|bdt|,|\s)/iu', '', $s);
        if (preg_match('/^\((.+)\)$/', $s, $m)) {
            $s = '-' . $m[1];
        }

        return $s !== '' && is_numeric($s) ? Dec::of($s) : null;
    }

    protected function blank($v): bool
    {
        return $v === null || (is_string($v) && trim($v) === '');
    }

    protected function digits(string $s): string
    {
        return strtr($s, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }

    protected function show($v): string
    {
        return mb_strimwidth(is_scalar($v) ? (string) $v : '', 0, 40, '…');
    }

    /**
     * "Navaron - (Zone) (habibur@sunlit)" → ["Navaron", "habibur@sunlit"];
     * anything else → [name without *, null].
     *
     * @return array{0: string, 1: ?string}
     */
    public static function splitName(?string $name): array
    {
        $name = trim((string) $name);
        if (preg_match('/^(.*?)\s*-\s*\(\s*zone\s*\)\s*\(([^)]*)\)\s*$/iu', $name, $m)) {
            return [trim(str_replace('*', '', $m[1])), trim($m[2]) ?: null];
        }

        return [trim(str_replace('*', '', $name)), null];
    }

    public function norm(string $name): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(str_replace('*', '', $name)));
    }

    /**
     * @return array<string, array{text:int, pos:int, neg:int, sum:float}>
     */
    protected function columnStats(array $rows): array
    {
        $stats = [];
        foreach ($rows as $cells) {
            foreach ($cells as $col => $v) {
                $stats[$col] ??= ['text' => 0, 'pos' => 0, 'neg' => 0, 'sum' => 0.0];
                $n = $this->number($v);
                if ($n === null) {
                    if (is_string($v) && trim($v) !== '') {
                        $stats[$col]['text']++;
                    }
                } elseif (Dec::isNegative($n)) {
                    $stats[$col]['neg']++;
                } elseif (bccomp($n, '0', Dec::SCALE) > 0) {
                    $stats[$col]['pos']++;
                    $stats[$col]['sum'] += (float) $n;
                }
            }
        }

        return $stats;
    }

    /**
     * Customers by normalised name and username.
     *
     * @return array<string, int>
     */
    protected function customerIndex(): array
    {
        $index = [];
        foreach (Customer::get(['id', 'name', 'username']) as $c) {
            foreach ([$c->name, $c->username] as $k) {
                if ($k) {
                    $index[$this->norm($k)] ??= $c->id;
                }
            }
        }

        return $index;
    }
}
