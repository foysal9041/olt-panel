<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\SharedWithRoles;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's own page of traffic graphs (as in LibreNMS), shared with
 * chosen roles or everyone with Graph Dashboards (SharedWithRoles).
 */
class GraphDashboard extends Model
{
    use LogsActivity, SharedWithRoles;

    public const REFRESH = [30, 60, 120, 300];

    protected $fillable = ['name', 'description', 'user_id', 'visibility', 'default_graph', 'refresh_seconds'];

    protected $casts = ['refresh_seconds' => 'integer'];

    public function roles()
    {
        return $this->hasMany(GraphDashboardRole::class);
    }

    public function widgets()
    {
        return $this->hasMany(GraphWidget::class)->orderBy('position')->orderBy('id');
    }

    protected function activityLogLabel(): string
    {
        return 'Graph Dashboard';
    }

    protected function activityLogTitle(): string
    {
        return (string) $this->name;
    }
}
