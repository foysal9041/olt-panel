<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use LogsActivity;

    protected $fillable = [
        'product_category_id',
        'name',
        'price',
        'billing_cycle',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
