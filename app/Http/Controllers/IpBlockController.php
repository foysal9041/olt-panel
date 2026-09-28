<?php

namespace App\Http\Controllers;

use App\Models\IpBlock;
use App\Models\IpPool;
use App\Support\SubnetRange;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IpBlockController extends Controller
{
    public function create()
    {
        return view('ip_blocks.create', ['block' => new IpBlock(['type' => 'public'])]);
    }

    public function store(Request $request)
    {
        $block = IpBlock::create($this->validated($request));

        return redirect()->route('ip-blocks.show', $block)->with('success', "Block {$block->cidr} added. Allocate subnets from the free rows below.");
    }

    public function show(IpBlock $ipBlock)
    {
        $allocations = $ipBlock->allocations()->get();

        // Private ranges used more than once across the whole IPAM.
        $privateCounts = IpPool::whereNotNull('private_subnet')->where('private_subnet', '!=', '')
            ->selectRaw('private_subnet, COUNT(*) as c')->groupBy('private_subnet')->pluck('c', 'private_subnet');

        return view('ip_blocks.show', [
            'block' => $ipBlock,
            'allocations' => $allocations,
            'rows' => $ipBlock->rows($allocations),
            'privateCounts' => $privateCounts,
        ]);
    }

    public function edit(IpBlock $ipBlock)
    {
        return view('ip_blocks.edit', ['block' => $ipBlock]);
    }

    public function update(Request $request, IpBlock $ipBlock)
    {
        $ipBlock->update($this->validated($request, $ipBlock));

        return redirect()->route('ip-blocks.show', $ipBlock)->with('success', 'Block updated.');
    }

    public function destroy(IpBlock $ipBlock)
    {
        if ($ipBlock->allocations()->exists()) {
            return back()->with('error', "Block {$ipBlock->cidr} still has allocated subnets. Release them first.");
        }

        $ipBlock->delete();

        return redirect()->route('ip-pools.index')->with('success', "Block {$ipBlock->cidr} deleted.");
    }

    protected function validated(Request $request, ?IpBlock $block = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'cidr' => 'required|string|max:50',
            'type' => 'required|in:public,private',
            'description' => 'nullable|string',
        ]);

        $cidr = str_contains($validated['cidr'], '/') ? SubnetRange::normalize($validated['cidr']) : null;

        if (! $cidr) {
            throw ValidationException::withMessages(['cidr' => 'Enter the block in CIDR form, e.g. 103.161.2.0/24.']);
        }

        foreach (IpBlock::when($block, fn ($q) => $q->whereKeyNot($block->id))->get() as $other) {
            if (SubnetRange::overlaps($cidr, $other->cidr)) {
                throw ValidationException::withMessages(['cidr' => "{$cidr} overlaps the existing block {$other->cidr} ({$other->name})."]);
            }
        }

        if ($block) {
            [$bMin, $bMax] = SubnetRange::parse($cidr);
            foreach ($block->allocations as $a) {
                [$min, $max] = SubnetRange::parse($a->subnet);
                if ($min < $bMin || $max > $bMax) {
                    throw ValidationException::withMessages(['cidr' => "{$a->subnet} ({$a->device}) would fall outside {$cidr}."]);
                }
            }
        }

        $validated['cidr'] = $cidr;

        return $validated;
    }
}
