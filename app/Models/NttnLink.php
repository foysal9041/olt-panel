<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class NttnLink extends Model
{
    use LogsActivity;

    protected $fillable = [
        'link_id',
        'provider',
        'address',
        'bandwidth',
        'public_ip_subnet',
        'private_ip_subnet',
        'peering_ip',
        'peering_vlan',
        'asn',
        'location',
        'zone',
        'status',
        'remarks',
    ];
}
