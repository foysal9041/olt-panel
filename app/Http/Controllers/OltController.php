<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksVlanOverlap;
use App\Models\Olt;
use App\Models\Zone;
use App\Models\IpPool;
use App\Models\LatencyTarget;
use App\Models\NetworkSwitch;
use App\Models\NttnLink;
use App\Models\SupportContact;
use App\Models\SwitchEvent;
use App\Models\SwitchPort;
use App\Models\Vlan;
use App\Services\NocOverview;
use App\Services\OltStatusChecker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class OltController extends Controller
{
    use ChecksVlanOverlap;

    public function dashboard()
    {
        $user = auth()->user();
        $overview = new NocOverview($user);
        $gate = fn ($ability) => Gate::allows($ability);

        $olts = $overview->olts();
        $issues = $overview->issues($olts);

        $switches = collect();
        $events = collect();
        $ports = null;
        if ($gate('access-olt-switches')) {
            $switches = NetworkSwitch::where('is_active', true)
                ->withCount([
                    'ports',
                    'ports as ports_up_count' => fn ($q) => $q->where('oper_status', SwitchPort::UP),
                    'ports as ports_sfp_count' => fn ($q) => $q->whereNotNull('rx_power'),
                    'ports as ports_rx_alarm_count' => fn ($q) => $q->where('rx_alarm', true),
                ])
                ->orderBy('status')
                ->orderBy('name')
                ->get();

            $ports = [
                'total' => $switches->sum('ports_count'),
                'up' => $switches->sum('ports_up_count'),
                'sfp' => $switches->sum('ports_sfp_count'),
                'rx_alarms' => $switches->sum('ports_rx_alarm_count'),
            ];

            $events = SwitchEvent::with('networkSwitch:id,name')->latest('occurred_at')->latest('id')->limit(10)->get();
        }

        $latencyTargets = $gate('access-latency-graphs')
            ? LatencyTarget::where('is_active', true)->orderByDesc('alert_active')->orderBy('group')->orderBy('name')->get()
            : collect();

        $inventory = array_filter([
            'zones' => $gate('access-olt-zones') ? ['Zones', Zone::count(), 'fas fa-map-marked-alt', 'zones.index'] : null,
            'vlans' => $gate('access-olt-vlans') ? ['VLANs', Vlan::count(), 'fas fa-stream', 'vlans.index'] : null,
            'ip' => $gate('access-olt-ip') ? ['IP Subnets', IpPool::count(), 'fas fa-globe', 'ip-pools.index'] : null,
            'nttn' => $gate('access-olt-nttn') ? ['NTTN Links', NttnLink::count(), 'fas fa-project-diagram', 'nttn-links.index'] : null,
            'support' => $gate('access-olt-support') ? ['Support Contacts', SupportContact::count(), 'fas fa-headset', 'support-contacts.index'] : null,
        ]);

        $events24h = $gate('access-olt-switches') ? SwitchEvent::where('occurred_at', '>=', now()->subDay())->count() : null;

        return view('olts.dashboard', compact(
            'olts', 'issues', 'switches', 'ports', 'events', 'events24h', 'latencyTargets', 'inventory'
        ));
    }

    public function index(Request $request)
    {
     $query = Olt::query();

     $user = auth()->user();

     if (
    !in_array(
        strtolower($user->role),
        ['admin', 'noc']
    )
) {
    $query->where(
        'zone',
        $user->zone
    );
}

   if ($request->filled('status')) {

     $query->where(
         'status',
         $request->status
      );
    }

   if ($request->filled('zone')) {

     $query->where(
         'zone',
         'like',
         '%' . $request->zone . '%'
      );
    }

  if ($request->filled('search')) {

     $query->where(function ($q) use ($request) {

         $q->where(
             'name',
             'like',
             '%' . $request->search . '%'
         )

         ->orWhere(
             'ip',
             'like',
             '%' . $request->search . '%'
         );

      });
    }

   if ($request->filled('vlan')) {

    $vlan = trim($request->vlan);

    $query->where(function ($q) use ($vlan) {

        $q->where(
            'vlan',
            'like',
            "%{$vlan}%"
        )

        ->orWhereRaw(
            "(
                vlan REGEXP '^[0-9]+-[0-9]+$'
                AND ? BETWEEN
                CAST(SUBSTRING_INDEX(vlan,'-',1) AS UNSIGNED)
                AND
                CAST(SUBSTRING_INDEX(vlan,'-',-1) AS UNSIGNED)
            )",
            [$vlan]
        );

    });
}

  $olts = $query
       ->orderBy('status', 'asc')
       ->orderBy('id', 'desc')
       ->get();

   return view(
       'olts.index',
       compact('olts')
   );


    }



    public function create()
    {
        $zones = Zone::orderBy('name')->pluck('name');

        return view('olts.create', compact('zones'));
    }

    public function store(Request $request, OltStatusChecker $checker)
    {
        $request->validate(
        [
           'zone'      => 'required|exists:zones,name',
           'brand'     => 'required',
           'vlan' => 'nullable|max:4094',
           'name'      => 'required',
           'ip'        => 'required|ip|unique:olts,ip',
           'username'  => 'required',
           'password'  => 'required',
           'snmp'      => 'required',
        ],
        [
        'ip.unique' => 'Sorry! This IP Address already exists.',
        'zone.exists' => 'Please select a valid zone from the list.',
        ]
        );

        $this->assertVlanNotOverlapping($request->vlan ?? '');

        $olt = Olt::create($request->except('status'));

        $checker->refresh($olt);

        return redirect()
            ->route('olt.index')
            ->with(
                'success',
                'OLT Added Successfully'
            );
    }

    public function show(Olt $olt)
    {
        return view(
            'olts.show',
            compact('olt')
        );
    }

    public function edit(Olt $olt)
    {
        $zones = Zone::orderBy('name')->pluck('name');

        return view(
            'olts.edit',
            compact('olt', 'zones')
        );
    }

    public function update(Request $request, Olt $olt, OltStatusChecker $checker)
    {
        $request->validate(
        [
            'zone'      => 'required|exists:zones,name',
            'brand'     => 'required',
            'vlan'      => 'nullable|max:4094',
            'name'      => 'required',
            'ip'        => 'required|ip|unique:olts,ip,' . $olt->id,
            'username'  => 'required',
            'password'  => 'required',
            'snmp'      => 'required',
        ],
        [
        'ip.unique' => 'Sorry! This IP Address already exists.',
        'zone.exists' => 'Please select a valid zone from the list.',
        ]
        );

        $this->assertVlanNotOverlapping($request->vlan ?? '', $olt->id);

        $olt->update($request->except('status'));

        $checker->refresh($olt);

        return redirect()
            ->route('olt.index')
            ->with(
                'success',
                'OLT Updated Successfully'
            );
    }

    public function destroy(Olt $olt)
    {
        $olt->delete();

        return redirect()
            ->route('olt.index')
            ->with(
                'success',
                'OLT Deleted Successfully'
            );
    }

    public function ping(Olt $olt)
    {
         $result = Process::timeout(15)
             ->run(['ping', '-n', '-c', '4', '-W', '2', $olt->ip]);

         return response('<pre>' . e($result->output() ?: $result->errorOutput()) . '</pre>');
    }

    public function web(Olt $olt)
    {
         $https = @fsockopen(
            $olt->ip,
            443,
            $errno,
            $errstr,
            2
        );

        if ($https) {

           fclose($https);

           return redirect()->away(
               "https://{$olt->ip}"
           );
        }

        return redirect()->away(
            "http://{$olt->ip}"
        );
   }

}
