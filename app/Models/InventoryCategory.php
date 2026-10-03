<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{
    use LogsActivity;

    protected $fillable = ['name'];

    public function items()
    {
        return $this->hasMany(InventoryItem::class);
    }
}
