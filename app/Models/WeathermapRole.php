<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A role a weathermap is shared with: view, or view and edit. */
class WeathermapRole extends Model
{
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['weathermap_id', 'role', 'can_edit'];

    protected $casts = ['can_edit' => 'boolean'];
}
