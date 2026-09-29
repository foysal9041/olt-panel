{{-- Reserve a free VLAN block in VLAN Management. Needs $id and $range. --}}
<div class="collapse mt-2" id="{{ $id }}">
    <form action="{{ route('vlans.store') }}" method="POST" class="form-row align-items-end border-top pt-2">
        @csrf
        <input type="hidden" name="vlan" value="{{ $range }}">
        <input type="hidden" name="status" value="reserved">
        <div class="col-md-4 form-group mb-1">
            <label class="small mb-0">Name / purpose</label>
            <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Client X P2P" required>
        </div>
        <div class="col-md-3 form-group mb-1">
            <label class="small mb-0">Zone</label>
            <select name="zone" class="form-control form-control-sm">
                <option value="">—</option>
                @foreach($zones as $zone)
                    <option value="{{ $zone }}" @selected($net === $zone)>{{ $zone }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 form-group mb-1">
            <label class="small mb-0">Remarks</label>
            <input type="text" name="remarks" class="form-control form-control-sm">
        </div>
        <div class="col-md-2 form-group mb-1">
            <button class="btn btn-success btn-sm btn-block">Reserve {{ $range }}</button>
        </div>
    </form>
</div>
