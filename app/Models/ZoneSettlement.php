<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Dec;
use Illuminate\Database\Eloquent\Model;

/**
 * One month's zone / partner settlement imported from Excel.
 */
class ZoneSettlement extends Model
{
    use LogsActivity;

    protected $fillable = [
        'month', 'invoice_date', 'bkash_percent', 'source_name', 'source_path', 'sheet_name',
        'mapping', 'original', 'blank_deduction_as_zero', 'notes', 'created_by', 'prepared_by', 'prepared_title',
        'posted_at', 'posted_on', 'posted_by',
    ];

    protected $casts = [
        'month' => 'date',
        'invoice_date' => 'date',
        'bkash_percent' => 'string',
        'mapping' => 'array',
        'original' => 'array',
        'blank_deduction_as_zero' => 'boolean',
        'posted_at' => 'datetime',
        'posted_on' => 'date',
    ];

    protected $hidden = ['original'];

    public function rows()
    {
        return $this->hasMany(ZoneSettlementRow::class)->orderBy('source_row');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** Net Bill has been posted to Accounts as income. */
    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    /**
     * Grand totals over the counted rows, plus the count.
     *
     * @return array{count:int, payment:string, deduction:string, invoice:string, difference:string, bkash:string, income:string}
     */
    public function totals(): array
    {
        $t = ['count' => 0, 'payment' => '0', 'deduction' => '0', 'invoice' => '0', 'difference' => '0', 'bkash' => '0', 'income' => '0'];

        foreach ($this->rows->where('included', true) as $row) {
            $c = $row->calc($this->bkash_percent);
            $t['count']++;
            foreach (['payment', 'deduction', 'invoice', 'difference', 'bkash', 'income'] as $k) {
                $t[$k] = Dec::add($t[$k], $c[$k]);
            }
        }

        return $t;
    }

    protected function activityLogLabel(): string
    {
        return 'Zone Settlement';
    }

    protected function activityLogTitle(): string
    {
        return ($this->month?->format('M Y') ?? '') . ' · ' . $this->source_name;
    }

    protected function activityLogExcept(): array
    {
        return ['original', 'mapping', 'created_at', 'updated_at'];
    }
}
