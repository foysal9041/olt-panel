<?php

namespace App\Http\Controllers;

use App\Models\NetworkSwitch;
use App\Models\SwitchEvent;
use Illuminate\Http\Request;

class SwitchEventController extends Controller
{
    public function index(Request $request)
    {
        $visible = NetworkSwitch::visibleTo($request->user())->pluck('name', 'id');

        $events = SwitchEvent::with(['networkSwitch', 'port'])
            ->whereIn('network_switch_id', $visible->keys())
            ->when($request->query('switch'), fn ($q, $id) => $q->where('network_switch_id', $id))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $switches = $visible->sort();

        return view('switches.events', compact('events', 'switches'));
    }
}
