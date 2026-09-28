<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class SupportContact extends Model
{
    use LogsActivity;

    protected $fillable = [
        'category',
        'vendor_name',
        'contact_person',
        'phone',
        'email',
        'remarks',
    ];
}
