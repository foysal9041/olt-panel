{{--
    Account + access fields shared by Add / Edit user.
    Expects $user (a new User on create) and $zones.
--}}
@php
    $editing = $user->exists;
    $err = fn ($f) => $errors->has($f) ? ' is-invalid' : '';
    $roles = config('roles');
    $currentRole = strtolower(old('role', $user->role ?: 'viewer'));
    $zoneValue = old('zone', $user->zone ?: 'all');
@endphp

<div class="row">
    <div class="col-lg-6">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-id-card mr-1 text-primary"></i> Account</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Full name <span class="text-danger">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control{{ $err('name') }}" required>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-row">
                    <div class="col-md-6 form-group">
                        <label>Username <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-at"></i></span></div>
                            <input type="text" name="username" value="{{ old('username', $user->username) }}" class="form-control{{ $err('username') }}" autocomplete="off" required>
                            @error('username') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control{{ $err('email') }}" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label>{{ $editing ? 'New password' : 'Password' }} @unless ($editing)<span class="text-danger">*</span>@endunless</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-lock"></i></span></div>
                        <input type="password" name="password" class="form-control{{ $err('password') }}" autocomplete="new-password" @unless ($editing) required @endunless>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    @if ($editing)
                        <small class="form-text text-muted">Leave blank to keep the current password.</small>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card acct-panel">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-user-shield mr-1 text-primary"></i> Role &amp; zone</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Role</label>
                    <div class="role-pick{{ $err('role') }}">
                        @foreach ($roles as $value => $r)
                            <label class="role-opt" style="--rc: {{ $r['color'] }}">
                                <input type="radio" name="role" value="{{ $value }}" @checked($currentRole === $value)>
                                <span class="role-ic"><i class="{{ $r['icon'] }}"></i></span>
                                <span class="role-tx"><b>{{ \App\Support\Ui::t($r['label']) }}</b><small>{{ \App\Support\Ui::t($r['description']) }}</small></span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="form-group">
                    <label>Zone</label>
                    <select name="zone" class="form-control js-zone-select{{ $err('zone') }}" data-placeholder="Choose a zone" data-clear="false" required>
                        <option value="all" data-name="All zones" @selected($zoneValue === 'all')>All zones</option>
                        @foreach ($zones as $zone)
                            <option value="{{ $zone }}" @selected($zoneValue === $zone)>{{ $zone }}</option>
                        @endforeach
                    </select>
                    @error('zone') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    <small class="form-text text-muted">Users limited to one zone only see that zone's OLTs and switches, unless device access below says otherwise.</small>
                </div>
                @isset($employees)
                    <div class="form-group">
                        <label>Employee record <small class="text-muted">(HR)</small></label>
                        <select name="employee_id" class="form-control{{ $err('employee_id') }}">
                            <option value="">— not linked —</option>
                            @foreach ($employees as $emp)
                                @php $takenBy = $emp->user && $emp->user->id !== $user->id ? $emp->user->name : null; @endphp
                                <option value="{{ $emp->id }}" @selected((int) old('employee_id', $user->employee_id) === $emp->id) @disabled($takenBy)>
                                    {{ $emp->name }}{{ $emp->designation ? ' — ' . $emp->designation : '' }}{{ $takenBy ? ' (' . $takenBy . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('employee_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        <small class="form-text text-muted">Lets them apply for leave and see their balance from the dashboard.</small>
                    </div>
                @endisset
                <div class="form-group mb-0">
                    <label>Status</label>
                    <div class="custom-control custom-switch">
                        <input type="hidden" name="status" value="0">
                        <input type="checkbox" class="custom-control-input" id="user-status" name="status" value="1"
                               @checked(old('status', $editing ? (int) $user->status : 1) == 1)>
                        <label class="custom-control-label" for="user-status">Active — can sign in</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
