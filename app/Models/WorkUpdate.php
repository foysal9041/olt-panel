<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line on a ticket's or task's timeline: a comment and/or what changed. */
class WorkUpdate extends Model
{
    protected $fillable = ['user_id', 'body', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
