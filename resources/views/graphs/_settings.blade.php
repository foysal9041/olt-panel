{{-- Dashboard settings and sharing (owner and admins). --}}
<div class="modal fade" id="dash-settings" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="POST" action="{{ route('graphs.update', $dashboard) }}" class="modal-content">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cog mr-1"></i> Dashboard settings</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="col-md-6 form-group">
                        <label>Name</label>
                        <input type="text" name="name" maxlength="100" class="form-control" value="{{ old('name', $dashboard->name) }}" required>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Description <small class="text-muted">(optional)</small></label>
                        <input type="text" name="description" maxlength="255" class="form-control" value="{{ old('description', $dashboard->description) }}">
                    </div>
                </div>
                <div class="form-row">
                    <div class="col-md-6 form-group">
                        <label>Default period</label>
                        <select name="default_graph" class="form-control">
                            @foreach (\App\Models\SwitchPortTraffic::GRAPHS as $key => [$title])
                                <option value="{{ $key }}" @selected(old('default_graph', $dashboard->default_graph) === $key)>{{ \App\Support\Ui::t($title) }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">For graphs that don't set their own.</small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Refresh every</label>
                        <select name="refresh_seconds" class="form-control">
                            @foreach (\App\Models\GraphDashboard::REFRESH as $s)
                                <option value="{{ $s }}" @selected((int) old('refresh_seconds', $dashboard->refresh_seconds) === $s)>{{ $s < 60 ? $s . ' seconds' : ($s / 60) . ' ' . \Illuminate\Support\Str::plural('minute', $s / 60) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <hr>
                @include('partials.share-roles', ['item' => $dashboard, 'editLabel' => 'View & edit graphs'])

                <div class="alert alert-light border small mt-3 mb-0">
                    <i class="fas fa-info-circle text-primary"></i>
                    Anyone who can open this dashboard sees all its graphs — even of switches outside their own device access.
                    Interfaces can only be added from switches the editor can see.
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" form="dash-delete" class="btn btn-link text-danger mr-auto"><i class="fas fa-trash-alt"></i> Delete dashboard</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>
<form method="POST" action="{{ route('graphs.destroy', $dashboard) }}" id="dash-delete" class="js-confirm-delete" data-confirm-message="Delete the dashboard &quot;{{ $dashboard->name }}&quot; and its {{ $dashboard->widgets()->count() }} graphs?">
    @csrf
    @method('DELETE')
</form>
