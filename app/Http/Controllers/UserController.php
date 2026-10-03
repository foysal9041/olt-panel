<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
   public function index()
  {
    $user = auth()->user();

    // When each user last did anything (and last signed in).
    $lastActivity = fn (?string $action = null) => \App\Models\ActivityLog::select('created_at')
        ->whereColumn('user_id', 'users.id')
        ->when($action, fn ($q) => $q->where('action', $action))
        ->latest('id')->limit(1);

    $users = User::with('modulePermissions')
        ->select('users.*')
        ->addSelect(['last_active_at' => $lastActivity(), 'last_login_at' => $lastActivity('login')])
        ->withCasts(['last_active_at' => 'datetime', 'last_login_at' => 'datetime'])
        ->when(strtolower($user->role) != 'admin', fn ($q) => $q->where('zone', $user->zone))
        ->latest()
        ->get();

    return view(
        'users.index',
        compact('users')
    );
 }

    public function create()
    {
    if (strtolower(auth()->user()->role) != 'admin') {
    abort(403);
    }


    $zones = \App\Models\Zone::names();

    $modules = config('modules');

   return view(
      'users.create',
      compact('zones', 'modules') + $this->deviceLists()
   );

 }

    public function store(Request $request)
{
    if (strtolower(auth()->user()->role) != 'admin') {
        abort(403);
    }

    $validated = $request->validate([
        'name'            => 'required',
        'email'           => 'required|email|unique:users,email',
        'username'        => 'required|unique:users,username',
        'password'        => 'required|min:6',
        'role'            => 'required|in:' . implode(',', array_keys(config('roles'))),
        'zone'            => ['required', $this->zoneRule()],
        'status'          => 'required',
        'permissions'     => 'nullable|array',
        'employee_id'     => 'nullable|exists:employees,id|unique:users,employee_id',
    ], ['employee_id.unique' => 'That employee is already linked to another login.']);

    $user = User::create([
        'name'            => $validated['name'],
        'email'           => $validated['email'],
        'username'        => $validated['username'],
        'password'        => Hash::make($validated['password']),
        'role'            => $validated['role'],
        'zone'            => $validated['zone'],
        'status'          => $validated['status'],
        'employee_id'     => $validated['employee_id'] ?? null,
    ]);

    $before = $this->accessSnapshot($user);
    $this->syncPermissions($user, $validated['permissions'] ?? []);
    $this->syncDeviceAccess($user, $request);
    $this->logAccessChange($user, $before);

    return redirect()
        ->route('users.index')
        ->with('success', 'User Created Successfully');
}

      public function show(Request $request, User $user)
      {
          $authUser = auth()->user();

          $canView = $authUser->role == 'admin'
              || ($authUser->role == 'operator' && $authUser->zone == $user->zone);

          if (! $canView) {
              abort(403);
          }

          $activity = $user->activityLogs()
              ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
              ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
              ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
              ->paginate(20)
              ->withQueryString();

          return view('users.show', compact('user', 'activity'));
      }

      public function edit(User $user)
      {
          $authUser = auth()->user();

          $zones = \App\Models\Zone::names();

          $modules = config('modules');
          $permissionState = $this->buildPermissionState($user);

          if ($authUser->role == 'admin') {
              return view('users.edit', compact('user', 'zones', 'modules', 'permissionState') + $this->deviceLists());
         }

         if (
             $authUser->role == 'operator' &&
             $authUser->zone == $user->zone
         ) {
             return view('users.edit', compact('user', 'zones', 'modules', 'permissionState') + $this->deviceLists());
         }

         abort(403);
     }

    public function update(Request $request, User $user)
    {
        $authUser = auth()->user();

        if (
            $authUser->role != 'admin' &&
            !(
                $authUser->role == 'operator' &&
                $authUser->zone == $user->zone
            )
        ) {
            abort(403);
        }

        $request->validate([
            'name'           => 'required',
            'email'          => 'required|email',
            'username'       => 'required',
            'role'           => 'required|in:' . implode(',', array_keys(config('roles'))),
            'zone'           => ['required', $this->zoneRule()],
            'permissions'    => 'nullable|array',
            'employee_id'    => 'nullable|exists:employees,id|unique:users,employee_id,' . $user->id,
        ], ['employee_id.unique' => 'That employee is already linked to another login.']);

        if ($authUser->role == 'operator') {

            $user->update([
                'name'            => $request->name,
                'email'           => $request->email,
                'username'        => $request->username,
                'zone'            => $request->zone,
                'status'          => $request->status,
            ]);

        } else {

            $user->update([
                'name'            => $request->name,
                'email'           => $request->email,
                'username'        => $request->username,
                'role'            => $request->role,
                'zone'            => $request->zone,
                'status'          => $request->status,
                'employee_id'     => $request->input('employee_id') ?: null,
            ]);

            // Only a full admin can change what modules and devices another user can reach.
            $before = $this->accessSnapshot($user);
            $this->syncPermissions($user, $request->input('permissions', []));
            $this->syncDeviceAccess($user, $request);
            $this->logAccessChange($user, $before);
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $user->save();
            \App\Models\ActivityLog::record('updated', "Changed the password of {$user->name}", $user);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'User Updated Successfully');
    }

    public function destroy(User $user)
    {
        if (auth()->user()->role != 'admin') {
            abort(403);
        }

        if ($user->id == auth()->id()) {
            return back()->with(
                'error',
                'You cannot delete your own account'
            );
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User Deleted Successfully');
    }

    /**
     * Rebuilds a user's module grants from the submitted
     * permissions[{module}][full] / permissions[{module}][sub][] payload.
     * Anything not present in config('modules') is silently ignored so a
     * tampered request can't grant access to a made-up module/submodule.
     *
     * @param  array<string, array{full?: mixed, sub?: list<string>}>  $permissions
     */
    private function syncPermissions(User $user, array $permissions): void
    {
        $user->modulePermissions()->delete();

        foreach (config('modules') as $moduleKey => $definition) {

            $entry = $permissions[$moduleKey] ?? null;

            if (! $entry) {
                continue;
            }

            if (! empty($entry['full'])) {
                $user->modulePermissions()->create([
                    'module' => $moduleKey,
                    'submodule' => '',
                ]);
                continue;
            }

            $validSubmodules = array_keys($definition['submodules'] ?? []);

            foreach (array_unique($entry['sub'] ?? []) as $submodule) {
                if (in_array($submodule, $validSubmodules, true)) {
                    $user->modulePermissions()->create([
                        'module' => $moduleKey,
                        'submodule' => $submodule,
                    ]);
                }
            }
        }
    }

    /**
     * Builds the [module => ['full' => bool, 'sub' => list<string>]]
     * structure the module-permissions partial needs to pre-check boxes
     * when editing an existing user.
     *
     * @return array<string, array{full: bool, sub: list<string>}>
     */
    private function buildPermissionState(User $user): array
    {
        $state = [];

        foreach (array_keys(config('modules')) as $moduleKey) {
            $state[$moduleKey] = [
                'full' => $user->hasWholeModuleAccess($moduleKey),
                'sub' => $user->submoduleKeys($moduleKey),
            ];
        }

        return $state;
    }

    /**
     * @return array{olts: \Illuminate\Support\Collection, switches: \Illuminate\Support\Collection}
     */
    private function deviceLists(): array
    {
        return [
            'employees' => \App\Models\Employee::with('user:id,employee_id,name')->orderBy('name')->get(['id', 'name', 'emp_code', 'designation']),
            'olts' => \App\Models\Olt::orderBy('zone')->orderBy('name')->get(['id', 'name', 'ip', 'zone']),
            'switches' => \App\Models\NetworkSwitch::orderBy('zone')->orderBy('name')->get(['id', 'name', 'ip', 'zone']),
        ];
    }

    /**
     * Save which OLTs / switches the user may see. The picked lists only
     * matter in "selected" mode, but are kept either way so switching
     * modes back and forth doesn't lose the selection.
     */
    private function syncDeviceAccess(User $user, Request $request): void
    {
        $modes = implode(',', array_keys(User::DEVICE_ACCESS));

        $validated = $request->validate([
            'olt_access' => 'nullable|in:' . $modes,
            'switch_access' => 'nullable|in:' . $modes,
            'allowed_olts' => 'nullable|array',
            'allowed_olts.*' => 'integer|exists:olts,id',
            'allowed_switches' => 'nullable|array',
            'allowed_switches.*' => 'integer|exists:network_switches,id',
        ]);

        if (! $request->has('olt_access')) {
            return;   // form without the Device Access section
        }

        $user->forceFill([
            'olt_access' => $validated['olt_access'] ?? 'zone',
            'switch_access' => $validated['switch_access'] ?? 'zone',
        ])->save();

        $user->allowedOlts()->sync($validated['allowed_olts'] ?? []);
        $user->allowedSwitches()->sync($validated['allowed_switches'] ?? []);
    }

    /**
     * A zone from Zones, or "all".
     */
    private function zoneRule(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) {
            if ($value !== 'all' && ! \App\Models\Zone::where('name', $value)->exists()) {
                $fail('Choose a zone from the list.');
            }
        };
    }

    /**
     * What a user can open, in words — for the activity log.
     *
     * @return array{modules: string, olts: string, switches: string}
     */
    private function accessSnapshot(User $user): array
    {
        $user->unsetRelation('modulePermissions');
        $modules = $user->modulePermissions()->get()->groupBy('module')->map(function ($grants, $key) {
            $label = config("modules.{$key}.label", $key);
            if ($grants->contains('submodule', '')) {
                return "{$label} (all)";
            }

            return $label . ': ' . $grants->map(fn ($g) => config("modules.{$key}.submodules.{$g->submodule}", $g->submodule))->implode(', ');
        })->sort()->implode('; ');

        $user->refresh();
        $devices = fn (string $kind, $relation) => (User::DEVICE_ACCESS[$user->{$kind . '_access'}] ?? $user->{$kind . '_access'})
            . ($user->{$kind . '_access'} === 'selected' ? ' (' . $user->{$relation}()->count() . ')' : '');

        return [
            'modules' => $modules ?: 'none',
            'olts' => $devices('olt', 'allowedOlts'),
            'switches' => $devices('switch', 'allowedSwitches'),
        ];
    }

    private function logAccessChange(User $user, array $before): void
    {
        $after = $this->accessSnapshot($user);
        $changes = [];

        foreach ($after as $key => $value) {
            if ($before[$key] !== $value) {
                $changes[$key] = ['old' => $before[$key], 'new' => $value];
            }
        }

        if ($changes) {
            \App\Models\ActivityLog::record('access', "Changed what {$user->name} can open", $user, $changes);
        }
    }
}
