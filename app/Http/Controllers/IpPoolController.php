<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksSubnetOverlap;
use App\Models\IpBlock;
use App\Models\IpPool;
use App\Models\Zone;
use App\Support\SubnetRange;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * IP Management: blocks overview (index) and the subnets allocated from
 * them. A subnet can also stand alone, without a block.
 */
class IpPoolController extends Controller
{
    use ChecksSubnetOverlap;

    public function index()
    {
        $blocks = IpBlock::with('allocations')->orderBy('type')->orderBy('cidr')->get();
        $standalone = IpPool::whereNull('ip_block_id')->orderBy('network')->get();

        return view('ip_pools.index', compact('blocks', 'standalone'));
    }

    public function create(Request $request)
    {
        $block = IpBlock::find($request->query('block'));

        $ipPool = new IpPool([
            'ip_block_id' => $block?->id,
            'subnet' => $request->query('subnet'),
            'type' => $block?->type ?? 'public',
            'status' => 'active',
        ]);

        return view('ip_pools.create', $this->formData($ipPool));
    }

    public function store(Request $request)
    {
        $pool = IpPool::create($this->validated($request));

        return $this->redirectFor($pool, 'Subnet ' . $pool->subnet . ' allocated.');
    }

    public function edit(IpPool $ipPool)
    {
        return view('ip_pools.edit', $this->formData($ipPool));
    }

    public function update(Request $request, IpPool $ipPool)
    {
        $ipPool->update($this->validated($request, $ipPool));

        return $this->redirectFor($ipPool, 'Subnet ' . $ipPool->subnet . ' updated.');
    }

    public function destroy(IpPool $ipPool)
    {
        $block = $ipPool->block;
        $subnet = $ipPool->subnet;
        $ipPool->delete();

        return ($block ? redirect()->route('ip-blocks.show', $block) : redirect()->route('ip-pools.index'))
            ->with('success', "Subnet {$subnet} released — it now shows as free.");
    }

    protected function formData(IpPool $ipPool): array
    {
        return [
            'ipPool' => $ipPool,
            'blocks' => IpBlock::orderBy('cidr')->get(),
            'zones' => Zone::orderBy('name')->pluck('name'),
            'devices' => IpPool::whereNotNull('device')->distinct()->orderBy('device')->pluck('device'),
            'purposes' => IpPool::whereNotNull('purpose')->distinct()->orderBy('purpose')->pluck('purpose'),
        ];
    }

    protected function redirectFor(IpPool $pool, string $message)
    {
        return ($pool->ip_block_id ? redirect()->route('ip-blocks.show', $pool->ip_block_id) : redirect()->route('ip-pools.index'))
            ->with('success', $message);
    }

    protected function validated(Request $request, ?IpPool $ipPool = null): array
    {
        $validated = $request->validate([
            'ip_block_id'    => 'nullable|exists:ip_blocks,id',
            'subnet'         => 'required|string|max:50',
            'device'         => 'nullable|string|max:255',
            'purpose'        => 'nullable|string|max:255',
            'private_subnet' => 'nullable|string|max:50',
            'gateway'        => 'nullable|ip',
            'type'           => 'required|in:public,private',
            'zone'           => 'nullable|string',
            'vlan'           => ['nullable', 'string', 'max:255', 'regex:/^[\d\s,\-]*$/'],
            'status'         => 'required|in:active,inactive',
            'description'    => 'nullable|string',
        ], [
            'vlan.regex' => 'VLANs should look like "210-213" or "2211-2214, 2435-2439".',
        ]);

        // Always store the real network address: .50/30 -> .48/30.
        $subnet = SubnetRange::normalize($validated['subnet']);

        if (! $subnet || ! str_contains($validated['subnet'], '/')) {
            throw ValidationException::withMessages(['subnet' => 'Enter the subnet in CIDR form, e.g. 103.161.2.48/30.']);
        }

        $validated['subnet'] = $subnet;

        if (IpPool::where('subnet', $subnet)->when($ipPool, fn ($q) => $q->whereKeyNot($ipPool->id))->exists()) {
            throw ValidationException::withMessages(['subnet' => "{$subnet} is already allocated."]);
        }

        if ($validated['ip_block_id'] ?? null) {
            $block = IpBlock::find($validated['ip_block_id']);
            [$bMin, $bMax] = $block->range();
            [$min, $max] = SubnetRange::parse($subnet);

            if ($min < $bMin || $max > $bMax) {
                throw ValidationException::withMessages(['subnet' => "{$subnet} is outside the block {$block->cidr}."]);
            }

            $validated['type'] = $block->type;
        }

        $this->assertSubnetNotOverlapping($subnet, $ipPool?->id);

        if (! empty($validated['private_subnet'])) {
            $validated['private_subnet'] = SubnetRange::normalize($validated['private_subnet']) ?? trim($validated['private_subnet']);
        }

        if (! empty($validated['vlan'])) {
            // "210 - 213 ,  220" -> "210-213, 220"
            $validated['vlan'] = collect(preg_split('/\s*,\s*/', trim($validated['vlan'])))
                ->map(fn ($v) => preg_replace('/\s*-\s*/', '-', trim($v)))
                ->filter()
                ->implode(', ');
        }

        return $validated;
    }
}
