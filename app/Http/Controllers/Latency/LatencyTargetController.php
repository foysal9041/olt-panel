<?php

namespace App\Http\Controllers\Latency;

use App\Http\Controllers\Controller;
use App\Models\LatencyTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class LatencyTargetController extends Controller
{
    public function index()
    {
        $targets = LatencyTarget::orderBy('group')->orderBy('name')->get();

        return view('latency.targets.index', compact('targets'));
    }

    public function create()
    {
        return view('latency.targets.create', [
            'target' => new LatencyTarget(['pings' => 20, 'is_active' => true, 'notify' => true]),
            'groups' => $this->groups(),
        ]);
    }

    public function store(Request $request)
    {
        $target = LatencyTarget::create($this->validated($request));

        // Take the first sample right away so the graph isn't empty until
        // the next scheduler tick.
        Artisan::call('app:probe-latency', ['--target' => $target->id]);

        return redirect()
            ->route('latency.targets.index')
            ->with('success', 'Target Added Successfully');
    }

    public function edit(LatencyTarget $target)
    {
        return view('latency.targets.edit', [
            'target' => $target,
            'groups' => $this->groups(),
        ]);
    }

    public function update(Request $request, LatencyTarget $target)
    {
        $target->update($this->validated($request));

        return redirect()
            ->route('latency.targets.index')
            ->with('success', 'Target Updated Successfully');
    }

    public function destroy(LatencyTarget $target)
    {
        $target->delete();

        return redirect()
            ->route('latency.targets.index')
            ->with('success', 'Target Deleted Successfully');
    }

    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'host' => [
                'required',
                'string',
                'max:253',
                function ($attribute, $value, $fail) {
                    $isIp = filter_var($value, FILTER_VALIDATE_IP) !== false;
                    $isHostname = preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i', $value);

                    if (! $isIp && ! $isHostname) {
                        $fail('The host must be a valid IP address or hostname.');
                    }
                },
            ],
            'group' => 'nullable|string|max:100',
            'pings' => 'required|integer|min:5|max:50',
            'latency_threshold' => 'nullable|numeric|min:0.1|max:10000',
            'loss_threshold' => 'nullable|numeric|min:1|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['host'] = trim($validated['host']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['notify'] = $request->boolean('notify');

        return $validated;
    }

    protected function groups()
    {
        return LatencyTarget::whereNotNull('group')->distinct()->orderBy('group')->pluck('group');
    }
}
