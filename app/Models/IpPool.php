<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class IpPool extends Model
{
    use LogsActivity;

    protected $fillable = [
        'subnet',
        'gateway',
        'type',
        'zone',
        'vlan',
        'status',
        'description',
    ];
}
