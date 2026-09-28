<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserModulePermission extends Model
{
    protected $fillable = [
        'user_id',
        'module',
        'submodule',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
