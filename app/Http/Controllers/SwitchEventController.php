<?php

namespace App\Http\Controllers;

use App\Models\NetworkSwitch;
use App\Models\SwitchEvent;
use Illuminate\Http\Request;

class SwitchEventController extends Controller
{
    public function index(Request $request)
    {
        $events = SwitchEvent::with(['networkSwitch', 'port'])
            ->when($request->query('switch'), fn ($q, $id) => $q->where('network_switch_id', $id))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $switches = NetworkSwitch::orderBy('name')->pluck('name', 'id');

        return view('switches.events', compact('events', 'switches'));
    }
}
