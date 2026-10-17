{{--
    "Who can open it" and the per-role view / edit table for something
    shared with roles (App\Models\Concerns\SharedWithRoles). Needs $item;
    $editLabel names the edit right, e.g. "View & edit graphs".
--}}
@php
    $shares = $item->roles->keyBy('role');
    $vis = old('visibility', $item->visibility);
@endphp
<label class="d-block">Who can open it</label>
@foreach ($item::VISIBILITY as $key => $label)
    <div class="custom-control custom-radio custom-control-inline">
        <input type="radio" id="vis-{{ $key }}" name="visibility" value="{{ $key }}" class="custom-control-input js-vis" @checked($vis === $key)>
        <label class="custom-control-label" for="vis-{{ $key }}">{{ $label }}</label>
    </div>
@endforeach

<div class="table-responsive mt-3 js-roles" @if ($vis !== 'roles') hidden @endif>
    <table class="table table-sm mb-1">
        <thead class="thead-light"><tr><th>Role</th><th class="text-center">No access</th><th class="text-center">View</th><th class="text-center">{{ $editLabel }}</th></tr></thead>
        <tbody>
            @foreach (config('roles') as $role => $def)
                @continue($role === 'admin')
                @php
                    $level = old("roles.$role", isset($shares[$role]) ? ($shares[$role]->can_edit ? 'edit' : 'view') : 'none');
                    $readOnly = in_array($role, ['viewer', 'owner'], true);
                @endphp
                <tr>
                    <td><i class="{{ $def['icon'] ?? 'fas fa-user' }}" style="color: {{ $def['color'] ?? '#64748b' }}"></i> {{ $def['label'] }}</td>
                    @foreach (['none', 'view', 'edit'] as $opt)
                        <td class="text-center">
                            <input type="radio" name="roles[{{ $role }}]" value="{{ $opt }}" @checked($level === $opt) @disabled($opt === 'edit' && $readOnly)
                                   @if ($opt === 'edit' && $readOnly) title="View-only role" @endif>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <small class="text-muted">Admins always open and edit everything. Only the owner and admins change these settings.</small>
</div>
