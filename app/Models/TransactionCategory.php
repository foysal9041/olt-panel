<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class TransactionCategory extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'type',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
