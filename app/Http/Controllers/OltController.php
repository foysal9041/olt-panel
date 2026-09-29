<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksIpConflict;
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
use Illuminate\Support\Facades\DB;
use App\Support\VlanRange;
use App\Services\VlanInventory;
use App\Services\IpInventory;
use App\Services\OltStatusChecker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class OltController extends Controller
{
    use ChecksIpConflict, ChecksVlanOverlap;

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
            $switches = NetworkSwitch::visibleTo($user)->where('is_active', true)
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

            $events = SwitchEvent::whereIn('network_switch_id', $switches->pluck('id'))->with('networkSwitch:id,name')->latest('occurred_at')->latest('id')->limit(10)->get();
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

        $events24h = $gate('access-olt-switches') ? SwitchEvent::whereIn('network_switch_id', $switches->pluck('id'))->where('occurred_at', '>=', now()->subDay())->count() : null;

        return view('olts.dashboard', compact(
            'olts', 'issues', 'switches', 'ports', 'events', 'events24h', 'latencyTargets', 'inventory'
        ));
    }

    public function index(Request $request)
    {
        // Only OLTs this user may see (all / their zone / hand-picked).
        $query = Olt::visibleTo(auth()->user());

   if ($request->filled('status')) {

     $query->where(
         'status',
         $request->status
      );
    }

   // Zone list for the filter dropdown: zones this user has OLTs in, with counts.
   $zoneStats = $this->zoneStats(Olt::visibleTo(auth()->user()));

   if ($request->filled('zone')) {

     $query->where('zone', $request->zone);
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

   $olts = $query
       ->orderBy('status', 'asc')
       ->orderBy('id', 'desc')
       ->get();

   // VLAN: a number matches any OLT whose VLAN list covers it ("1232-1235, 1291-1294").
   if ($request->filled('vlan')) {
       $vlan = trim($request->vlan);

       $olts = $olts->filter(function (Olt $olt) use ($vlan) {
           if (ctype_digit($vlan)) {
               foreach (VlanRange::parseList($olt->vlan) as [$min, $max]) {
                   if ((int) $vlan >= $min && (int) $vlan <= $max) {
                       return true;
                   }
               }

               return false;
           }

           return str_contains((string) $olt->vlan, $vlan);
       })->values();
   }

   return view(
       'olts.index',
       compact('olts', 'zoneStats')
   );
    }



    public function create(Request $request)
    {
        $zones = Zone::orderBy('name')->pluck('name');
        $zone = $zones->contains($request->query('zone')) ? $request->query('zone') : null;
        $zoneStats = $this->zoneStats(Olt::query(), allZones: true);

        return view('olts.create', compact('zones', 'zone', 'zoneStats'));
    }

    /**
     * Per zone: OLTs, how many are down, and whether it's a POP with its
     * own VLANs — for the zone dropdowns. With $allZones every zone is
     * listed, even those without OLTs yet.
     *
     * @return \Illuminate\Support\Collection<string, array{count: int, down: int, pop: bool}>
     */
    private function zoneStats($query, bool $allZones = false)
    {
        $counts = $query->whereNotNull('zone')->where('zone', '!=', '')
            ->selectRaw('zone, COUNT(*) as c, SUM(status = 0) as d')->groupBy('zone')->get()->keyBy('zone');
        $pops = Zone::where('own_vlans', true)->pluck('name')->flip();
        $names = $allZones ? Zone::orderBy('name')->pluck('name') : $counts->keys()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();

        return $names->mapWithKeys(fn ($name) => [$name => [
            'count' => (int) ($counts[$name]->c ?? 0),
            'down' => (int) ($counts[$name]->d ?? 0),
            'pop' => isset($pops[$name]),
        ]]);
    }

    /**
     * Add one or more OLTs to a zone. Brand, login and SNMP are shared by
     * every row (a row may set its own brand); each row has its name, IP
     * and VLAN, all checked against each other and everything recorded.
     */
    public function store(Request $request, OltStatusChecker $checker)
    {
        // Drop rows left completely empty; tidy VLANs ("1422 - 1425" -> "1422-1425").
        $rows = collect($request->input('olts', []))
            ->map(fn ($r) => array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) $r))
            ->filter(fn ($r) => filled($r['name'] ?? null) || filled($r['ip'] ?? null) || filled($r['vlan'] ?? null))
            ->map(function ($r) {
                if (filled($r['vlan'] ?? null)) {
                    $r['vlan'] = collect(preg_split('/\s*,\s*/', $r['vlan']))
                        ->map(fn ($v) => preg_replace('/\s*-\s*/', '-', trim($v)))->filter()->implode(', ');
                }

                return $r;
            })
            ->values()
            ->all();
        $request->merge(['olts' => $rows]);

        $validated = $request->validate([
            'zone' => 'required|exists:zones,name',
            'brand' => 'required|string|max:100',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'snmp' => 'nullable|string|max:255',
            'olts' => 'required|array|min:1|max:50',
            'olts.*.name' => 'required|string|max:255|distinct:ignore_case',
            'olts.*.ip' => 'required|ip|distinct|unique:olts,ip',
            'olts.*.vlan' => ['nullable', 'string', 'max:100', 'regex:/^\d{1,4}(-\d{1,4})?(, ?\d{1,4}(-\d{1,4})?)*$/'],
            'olts.*.brand' => 'nullable|string|max:100',
        ], [
            'zone.exists' => 'Please select a valid zone from the list.',
            'olts.required' => 'Add at least one OLT.',
            'olts.*.name.required' => 'Row :position: enter the OLT name.',
            'olts.*.name.distinct' => 'Row :position: this name is used on another row.',
            'olts.*.ip.required' => 'Row :position: enter the IP address.',
            'olts.*.ip.ip' => 'Row :position: that is not a valid IP address.',
            'olts.*.ip.distinct' => 'Row :position: this IP is used on another row.',
            'olts.*.ip.unique' => 'Row :position: an OLT with this IP already exists.',
            'olts.*.vlan.regex' => 'Row :position: VLAN should look like 1422-1425 (or a list: 1232-1235, 1291-1294).',
        ]);

        // Against everything already recorded (other devices, subnets, VLANs) …
        $errors = [];
        foreach ($validated['olts'] as $i => $row) {
            foreach ([
                fn () => $this->assertIpNotUsed($row['ip'], 'olt', fieldName: "olts.{$i}.ip"),
                fn () => $this->assertVlanNotOverlapping($row['vlan'] ?? '', zone: $validated['zone'], fieldName: "olts.{$i}.vlan"),
            ] as $check) {
                try {
                    $check();
                } catch (\Illuminate\Validation\ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        $errors[$field] = 'Row ' . ($i + 1) . ': ' . $messages[0];
                    }
                }
            }
        }

        // … and against each other.
        foreach ($validated['olts'] as $i => $a) {
            foreach (array_slice($validated['olts'], $i + 1, null, true) as $j => $b) {
                foreach (VlanRange::parseList($a['vlan'] ?? '') as [$aMin, $aMax]) {
                    foreach (VlanRange::parseList($b['vlan'] ?? '') as [$bMin, $bMax]) {
                        if ($aMin <= $bMax && $bMin <= $aMax) {
                            $errors["olts.{$j}.vlan"] ??= 'Row ' . ($j + 1) . ': VLAN overlaps row ' . ($i + 1) . " ({$a['vlan']}).";
                        }
                    }
                }
            }
        }

        if ($errors) {
            ksort($errors, SORT_NATURAL);
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        $created = DB::transaction(fn () => collect($validated['olts'])->map(fn ($row) => Olt::create([
            'zone' => $validated['zone'],
            'name' => $row['name'],
            'ip' => $row['ip'],
            'vlan' => $row['vlan'] ?? null,
            'brand' => filled($row['brand'] ?? null) ? $row['brand'] : $validated['brand'],
            'username' => $validated['username'],
            'password' => $validated['password'],
            'snmp' => $validated['snmp'] ?? null,
        ])));

        if (auth()->user()->deviceAccess('olt') === 'selected') {
            auth()->user()->allowedOlts()->syncWithoutDetaching($created->pluck('id'));
        }

        // Ping them all at once for their first status.
        foreach ($checker->check($created) as $id => $up) {
            Olt::whereKey($id)->update(['status' => $up ? 1 : 0]);
        }

        return redirect()
            ->route('olt.index', ['zone' => $validated['zone']])
            ->with('success', $created->count() === 1
                ? "OLT {$created->first()->name} added to {$validated['zone']}."
                : "{$created->count()} OLTs added to {$validated['zone']}: " . $created->pluck('name')->implode(', ') . '.');
    }

    /**
     * Suggested name, IP and VLAN for $count new OLTs in a zone, following
     * what the zone already has:
     *  - name: "<zone's OLT prefix> OLT-<next number>";
     *  - IP: the device address (.2) of the next free /30 in the zone's
     *    /24 (then the other OLT /24s), in sequence after the last used,
     *    then free gaps;
     *  - VLAN: the next free block of $vlans in sequence, in the zone's
     *    VLAN space (its own for a POP, else the core).
     */
    public function suggest(Request $request)
    {
        $zone = Zone::where('name', (string) $request->query('zone'))->first();
        abort_unless($zone, 404);

        $count = max(1, min(50, (int) $request->query('count', 1)));
        $vlanSize = max(1, min(64, (int) $request->query('vlans', 4)));
        $allOlts = Olt::get(['id', 'name', 'ip', 'zone', 'vlan']);
        $zoneOlts = $allOlts->where('zone', $zone->name);

        // Names: reuse the zone's prefix ("Kaliganj" for "Sunlit Kaliganj_Ovi").
        $prefix = $zoneOlts->map(fn ($o) => preg_match('/^(.*?)\s*OLT-?\s*\d+$/i', $o->name, $m) ? trim($m[1]) : null)
            ->filter()->countBy()->sortDesc()->keys()->first() ?? $zone->name;
        $number = $zoneOlts->map(fn ($o) => preg_match('/(\d+)\s*$/', $o->name, $m) ? (int) $m[1] : 0)->max() ?? 0;

        // IPs: /24s to look in — the zone's own first, then other OLT /24s of the same kind (POP / core).
        $inventory = new IpInventory;
        $slash24 = fn ($ip) => ($long = ip2long((string) $ip)) !== false ? long2ip($long & 0xFFFFFF00) . '/24' : null;
        $vlanInventory = new VlanInventory;
        $sameKind = $allOlts->filter(fn ($o) => (bool) $vlanInventory->networkFor($o->zone) === (bool) $zone->own_vlans);
        $ranges = $zoneOlts->map(fn ($o) => $slash24($o->ip))->filter()->countBy()->sortDesc()->keys()
            ->merge($sameKind->map(fn ($o) => $slash24($o->ip))->filter()->countBy()->sortDesc()->keys())
            ->merge($allOlts->map(fn ($o) => $slash24($o->ip))->filter()->countBy()->sortDesc()->keys())
            ->unique()->values();

        $ips = [];
        foreach ($ranges as $cidr) {
            [$from, $to] = \App\Support\SubnetRange::parse($cidr);

            // In sequence after the last OLT in this /24, then the free gaps,
            // then after whatever else is recorded there.
            $lastOlt = $allOlts->map(fn ($o) => ip2long((string) $o->ip))->filter(fn ($l) => $l !== false && $l >= $from && $l <= $to)->max();
            $found = $inventory->findFree(30, 1, $from, $to, 50);
            $candidates = [];
            for ($b = $lastOlt !== null ? ($lastOlt & ~3) + 4 : $to + 1; $b + 3 <= $to; $b += 4) {
                $candidates[] = $b;
            }
            foreach ($found['gaps'] as $g) {
                for ($b = $g['free'][0]; $b + 3 <= $g['free'][1]; $b += 4) {
                    $candidates[] = $b;
                }
            }
            for ($b = $found['next'][0][0] ?? $to + 1; $b + 3 <= $to; $b += 4) {
                $candidates[] = $b;
            }

            foreach (array_unique($candidates) as $b) {
                if (count($ips) >= $count) {
                    break;
                }
                if ($inventory->usage($b, $b + 3)['used'] === 0) {
                    $ips[] = long2ip($b + 2);
                }
            }

            if (count($ips) >= $count) {
                break;
            }
        }

        // VLANs: the thousand the zone's OLTs use (core OLTs live in 1000s, POPs in 100s).
        $network = $vlanInventory->networkFor($zone->name);
        $firstVlans = $zoneOlts->flatMap(fn ($o) => array_column(VlanRange::parseList($o->vlan), 0));
        $sameKindVlans = $sameKind->flatMap(fn ($o) => array_column(VlanRange::parseList($o->vlan), 0));
        $seed = $firstVlans->first() ?? $sameKindVlans->sort()->values()->get(intdiv($sameKindVlans->count(), 2)) ?? ($network ? 101 : 1001);
        $bucket = intdiv((int) $seed, 1000) * 1000;
        [$vFrom, $vTo] = [max(2, $bucket), min(4094, $bucket + 999)];

        $vlans = [];
        $free = $vlanInventory->findFree($vlanSize * $count, $vFrom, $vTo, network: $network);
        if ($free['next']) {
            for ($i = 0; $i < $count; $i++) {
                $start = $free['next'][0] + $i * $vlanSize;
                $vlans[] = VlanRange::format($start, $start + $vlanSize - 1);
            }
        } else {
            foreach ($vlanInventory->findFree($vlanSize, $vFrom, $vTo, maxGaps: $count, network: $network)['gaps'] as $g) {
                $vlans[] = VlanRange::format($g[0], $g[1]);
            }
        }

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'name' => $prefix . ' OLT-' . ($number + $i + 1),
                'ip' => $ips[$i] ?? null,
                'vlan' => $vlans[$i] ?? null,
            ];
        }

        return response()->json([
            'rows' => $rows,
            'note' => 'IPs: next free /30s in ' . ($ranges->first() ?? '—') . ' (device .2) · VLANs: next ' . $vlanSize
                . ' in ' . VlanInventory::networkLabel($network) . " {$vFrom}–{$vTo}",
        ]);
    }

    public function show(Olt $olt)
    {
        abort_unless(auth()->user()->canSeeOlt($olt), 403);

        return view(
            'olts.show',
            compact('olt')
        );
    }

    public function edit(Olt $olt)
    {
        abort_unless(auth()->user()->canSeeOlt($olt), 403);

        $zones = Zone::orderBy('name')->pluck('name');
        $zoneStats = $this->zoneStats(Olt::query(), allZones: true);

        return view(
            'olts.edit',
            compact('olt', 'zones', 'zoneStats')
        );
    }

    public function update(Request $request, Olt $olt, OltStatusChecker $checker)
    {
        abort_unless(auth()->user()->canSeeOlt($olt), 403);

        $request->validate(
        [
            'zone'      => 'required|exists:zones,name',
            'brand'     => 'required',
            'vlan'      => 'nullable|max:4094',
            'name'      => 'required',
            'ip'        => 'required|ip|unique:olts,ip,' . $olt->id,
            'username'  => 'required',
            'password'  => 'required',
            'snmp'      => 'nullable|string|max:255',
        ],
        [
        'ip.unique' => 'Sorry! This IP Address already exists.',
        'zone.exists' => 'Please select a valid zone from the list.',
        ]
        );

        $this->assertIpNotUsed($request->ip, 'olt', $olt->id);
        $this->assertVlanNotOverlapping($request->vlan ?? '', $olt->id, zone: $request->zone);

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
        abort_unless(auth()->user()->canSeeOlt($olt), 403);

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
        abort_unless(auth()->user()->canSeeOlt($olt), 403);

         $result = Process::timeout(15)
             ->run(['ping', '-n', '-c', '4', '-W', '2', $olt->ip]);

         return response('<pre>' . e($result->output() ?: $result->errorOutput()) . '</pre>');
    }

    public function web(Olt $olt)
    {
        abort_unless(auth()->user()->canSeeOlt($olt), 403);

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
