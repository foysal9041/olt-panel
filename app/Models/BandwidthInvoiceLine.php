<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BandwidthInvoiceLine extends Model
{
    protected $fillable = ['bandwidth_invoice_id', 'bandwidth_type_id', 'label', 'period_from', 'period_to', 'rate', 'mbps', 'amount', 'remark', 'sort'];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'rate' => 'string',
        'mbps' => 'string',
        'amount' => 'string',
    ];

    public function invoice()
    {
        return $this->belongsTo(BandwidthInvoice::class, 'bandwidth_invoice_id');
    }

    /** "1 Aug to 31 Aug" */
    public function periodLabel(): string
    {
        if (! $this->period_from) {
            return '';
        }

        return $this->period_from->format('j M') . ' to ' . ($this->period_to ?? $this->period_from)->format('j M');
    }
}
