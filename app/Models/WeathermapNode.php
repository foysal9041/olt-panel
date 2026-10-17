<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A box on a weathermap: a switch / router (shows its up/down state) or a free label such as "IIG". */
class WeathermapNode extends Model
{
    /** Icon => [label, Font Awesome 5 solid glyph]. */
    public const ICONS = [
        'router' => ['Router', "\u{f4d7}"],
        'switch' => ['Switch', "\u{f233}"],
        'cloud' => ['Cloud / upstream', "\u{f0c2}"],
        'olt' => ['OLT', "\u{f6ff}"],
        'server' => ['Server', "\u{f1c0}"],
        'building' => ['POP / building', "\u{f1ad}"],
        'users' => ['Customers', "\u{f0c0}"],
    ];

    protected $fillable = ['weathermap_id', 'network_switch_id', 'label', 'icon', 'x', 'y'];

    protected $casts = ['x' => 'integer', 'y' => 'integer'];

    public function device()
    {
        return $this->belongsTo(NetworkSwitch::class, 'network_switch_id');
    }

    /** up / down / none (a free label has no state). */
    public function state(): string
    {
        if (! $this->device) {
            return 'none';
        }

        return $this->device->status === 1 ? 'up' : 'down';
    }
}
