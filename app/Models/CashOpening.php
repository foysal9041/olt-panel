<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class CashOpening extends Model
{
    use LogsActivity;

    protected $fillable = ['date', 'amount', 'note', 'recorded_by'];

    protected $casts = [
        'date' => 'date',
        'amount' => 'float',
    ];

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected function activityLogLabel(): string
    {
        return 'Cash on hand';
    }

    protected function activityLogTitle(): string
    {
        return $this->date?->format('d M Y') . ' · ৳' . number_format((float) $this->amount, 2);
    }
}
