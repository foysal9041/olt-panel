{{-- Weathermap settings and sharing (owner and admins). --}}
<div class="modal fade" id="map-settings" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('maps.update', $map) }}" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cog mr-1"></i> Weathermap settings</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="col-md-6 form-group">
                        <label>Name</label>
                        <input type="text" name="name" maxlength="100" class="form-control" value="{{ old('name', $map->name) }}" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Description <small class="text-muted">(optional)</small></label>
                        <input type="text" name="description" maxlength="255" class="form-control" value="{{ old('description', $map->description) }}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="col-6 col-md-3 form-group">
                        <label>Canvas width</label>
                        <input type="number" name="width" min="600" max="4000" step="10" class="form-control" value="{{ old('width', $map->width) }}">
                    </div>
                    <div class="col-6 col-md-3 form-group">
                        <label>Canvas height</label>
                        <input type="number" name="height" min="400" max="3000" step="10" class="form-control" value="{{ old('height', $map->height) }}">
                    </div>
                    <div class="col-6 col-md-3 form-group">
                        <label>Link labels</label>
                        <select name="label_mode" class="form-control">
                            @foreach (\App\Models\Weathermap::LABEL_MODES as $key => $label)
                                <option value="{{ $key }}" @selected(old('label_mode', $map->label_mode) === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3 form-group">
                        <label>Refresh every</label>
                        <select name="refresh_seconds" class="form-control">
                            @foreach (\App\Models\Weathermap::REFRESH as $s)
                                <option value="{{ $s }}" @selected((int) old('refresh_seconds', $map->refresh_seconds) === $s)>{{ $s < 60 ? $s . ' seconds' : ($s / 60) . ' ' . \Illuminate\Support\Str::plural('minute', $s / 60) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <hr>
                @include('partials.share-roles', ['item' => $map, 'editLabel' => 'View & edit the map'])

                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="fas fa-info-circle text-primary"></i>
                    Anyone who can open this map sees all its links' traffic — even of devices outside their own device access.
                    Devices and interfaces can only be added from switches and routers the editor can see.
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" form="map-delete" class="btn btn-link text-danger mr-auto"><i class="fas fa-trash-alt"></i> Delete weathermap</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>
<form method="POST" action="{{ route('maps.destroy', $map) }}" id="map-delete" class="js-confirm-delete" data-confirm-message="Delete the weathermap &quot;{{ $map->name }}&quot;?">
    @csrf
    @method('DELETE')
</form>
