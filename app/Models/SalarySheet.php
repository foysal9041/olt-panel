<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalarySheet extends Model
{
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
}
