<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Vlan extends Model
{
    use LogsActivity;

    protected $fillable = [
        'vlan',
        'name',
        'zone',
        'status',
        'remarks',
    ];
}
