<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\SharedWithRoles;
use Illuminate\Database\Eloquent\Model;

/**
 * A network weathermap: nodes on a canvas and links coloured by their
 * interface's live utilisation, shared like graph dashboards.
 */
class Weathermap extends Model
{
    use LogsActivity, SharedWithRoles;

    public const REFRESH = [30, 60, 120, 300];

    /** What the link labels show. */
    public const LABEL_MODES = ['bps' => 'Bits per second', 'percent' => 'Utilisation %', 'none' => 'No labels'];

    protected $fillable = ['name', 'description', 'user_id', 'visibility', 'width', 'height', 'label_mode', 'refresh_seconds'];

    protected $casts = ['width' => 'integer', 'height' => 'integer', 'refresh_seconds' => 'integer'];

    public function roles()
    {
        return $this->hasMany(WeathermapRole::class);
    }

    public function nodes()
    {
        return $this->hasMany(WeathermapNode::class)->orderBy('id');
    }

    public function links()
    {
        return $this->hasMany(WeathermapLink::class)->orderBy('id');
    }

    protected function activityLogLabel(): string
    {
        return 'Weathermap';
    }

    protected function activityLogTitle(): string
    {
        return (string) $this->name;
    }
}
