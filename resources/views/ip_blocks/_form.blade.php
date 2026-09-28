@php $err = fn ($f) => $errors->has($f) ? ' is-invalid' : ''; @endphp

<form method="POST" action="{{ $action }}">
    @csrf
    @if ($method !== 'POST') @method($method) @endif

    <div class="card acct-panel" style="max-width: 720px">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="cidr">Block (CIDR) <span class="text-danger">*</span></label>
                    <input type="text" id="cidr" name="cidr" class="form-control{{ $err('cidr') }}" style="font-family: ui-monospace, monospace"
                           value="{{ old('cidr', $block->cidr) }}" placeholder="103.161.2.0/24" required autofocus>
                    @error('cidr') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 form-group">
                    <label for="name">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control{{ $err('name') }}"
                           value="{{ old('name', $block->name) }}" placeholder="e.g. Public IP — Upstream" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="col-md-6 form-group">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="public" @selected(old('type', $block->type) === 'public')>Public</option>
                        <option value="private" @selected(old('type', $block->type) === 'private')>Private</option>
                    </select>
                </div>
                <div class="col-12 form-group mb-0">
                    <label>Description</label>
                    <textarea name="description" rows="2" class="form-control" placeholder="Upstream, allocation date, notes…">{{ old('description', $block->description) }}</textarea>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <div>
                @if ($block->exists)
                    <button type="submit" form="delete-block" class="btn btn-outline-danger"><i class="fas fa-trash"></i> Delete Block</button>
                @endif
            </div>
            <div>
                <a href="{{ $block->exists ? route('ip-blocks.show', $block) : route('ip-pools.index') }}" class="btn btn-light">Cancel</a>
                <button class="btn btn-primary"><i class="fas fa-save"></i> Save Block</button>
            </div>
        </div>
    </div>
</form>

@if ($block->exists)
    <form id="delete-block" action="{{ route('ip-blocks.destroy', $block) }}" method="POST" class="js-confirm-delete"
          data-confirm-message="Delete block {{ $block->cidr }}? Only possible when no subnets are allocated from it.">
        @csrf
        @method('DELETE')
    </form>
@endif
