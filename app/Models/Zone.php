<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
    ];

    /**
     * olts.zone is a plain string matching zones.name (not a foreign key to
     * zones.id) — same convention used by users/employees/customers, kept
     * that way here to avoid a wider foreign-key migration across all of
     * them just for this.
     */
    public function olts()
    {
        return $this->hasMany(Olt::class, 'zone', 'name');
    }
}
