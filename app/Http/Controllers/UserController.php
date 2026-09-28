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

    if (strtolower($user->role) == 'admin') {

        $users = User::with('modulePermissions')->latest()->get();

    } else {

        $users = User::with('modulePermissions')->where(
            'zone',
            $user->zone
        )->latest()->get();
    }

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


    $zones = \App\Models\Olt::select('zone')
         ->whereNotNull('zone')
         ->where('zone', '!=', '')
         ->distinct()
         ->orderBy('zone')
         ->pluck('zone');

    $modules = config('modules');

   return view(
      'users.create',
      compact('zones', 'modules')
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
        'role'            => 'required',
        'zone'            => 'required',
        'status'          => 'required',
        'permissions'     => 'nullable|array',
    ]);

    $user = User::create([
        'name'            => $validated['name'],
        'email'           => $validated['email'],
        'username'        => $validated['username'],
        'password'        => Hash::make($validated['password']),
        'role'            => $validated['role'],
        'zone'            => $validated['zone'],
        'status'          => $validated['status'],
    ]);

    $this->syncPermissions($user, $validated['permissions'] ?? []);

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

          $zones = \App\Models\Olt::select('zone')
               ->whereNotNull('zone')
               ->where('zone', '!=', '')
               ->distinct()
               ->orderBy('zone')
               ->pluck('zone');

          $modules = config('modules');
          $permissionState = $this->buildPermissionState($user);

          if ($authUser->role == 'admin') {
              return view('users.edit', compact('user', 'zones', 'modules', 'permissionState'));
         }

         if (
             $authUser->role == 'operator' &&
             $authUser->zone == $user->zone
         ) {
             return view('users.edit', compact('user', 'zones', 'modules', 'permissionState'));
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
            'role'           => 'required',
            'permissions'    => 'nullable|array',
        ]);

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
            ]);

            // Only a full admin can change what modules another user can reach.
            $this->syncPermissions($user, $request->input('permissions', []));
        }

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
            $user->save();
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
}
