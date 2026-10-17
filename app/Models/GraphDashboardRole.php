<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A role a graph dashboard is shared with: view, or view and edit its graphs. */
class GraphDashboardRole extends Model
{
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['graph_dashboard_id', 'role', 'can_edit'];

    protected $casts = ['can_edit' => 'boolean'];

    public function dashboard()
    {
        return $this->belongsTo(GraphDashboard::class, 'graph_dashboard_id');
    }
}
