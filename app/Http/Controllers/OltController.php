<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksVlanOverlap;
use App\Models\Olt;
use App\Models\Zone;
use App\Services\OltStatusChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;

class OltController extends Controller
{
    use ChecksVlanOverlap;

    public function dashboard()
    {
        $user = auth()->user();

        if (in_array(strtolower($user->role), ['admin', 'noc'])) {

            $olts = Olt::latest()->get();

            $totalOlt = Olt::count();

            $onlineOlt = Olt::where('status', 1)->count();

            $offlineOlt = Olt::where('status', 0)->count();

        } else {

            $olts = Olt::where('zone', $user->zone)->latest()->get();

            $totalOlt = Olt::where('zone', $user->zone)->count();

            $onlineOlt = Olt::where('zone', $user->zone)->where('status', 1)->count();

            $offlineOlt = Olt::where('zone', $user->zone)->where('status', 0)->count();
        }

        return view(
            'olts.dashboard',
            compact('olts', 'totalOlt', 'onlineOlt', 'offlineOlt')
        );
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
           'model'     => 'required',
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
            'model'     => 'required',
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
