<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class SalarySheet extends Model
{
    use LogsActivity;

    protected $fillable = ['month', 'posted_at', 'notes'];

    protected $casts = [
        'month' => 'date',
        'posted_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(SalarySheetItem::class)->orderBy('sort')->orderBy('id');
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    protected function activityLogLabel(): string
    {
        return 'Salary Sheet';
    }

    protected function activityLogTitle(): string
    {
        return ($this->month?->format('M Y') ?? '#' . $this->getKey()) . ($this->posted_at ? ' (posted)' : '');
    }
}
