@extends('adminlte::page')

@section('title','Users Management')

@section('content')

@if(session('success'))

<div class="alert alert-success">
    {{ session('success') }}
</div>
@endif

<div class="card">

<div class="card-header d-flex justify-content-between">

    <h3 class="card-title">
        Users Management
    </h3>

    @if(strtolower(auth()->user()->role) == 'admin')

<a href="{{ route('users.create') }}"
   class="btn btn-primary">
    <i class="fas fa-plus"></i>
    Add User
</a>

@endif

</div>

<div class="card-body">

    <table class="table table-bordered table-striped data-table">

        <thead>

        <tr>

            <th>ID</th>
            <th>Name</th>
            <th>Username</th>
            <th>Role</th>
            <th>Status</th>
            <th>Zone</th>
            <th>Modules</th>
            <th width="250">Actions</th>

        </tr>

        </thead>

        <tbody>

        @foreach($users as $user)

        <tr>

            <td>{{ $loop->iteration }}</td>

            <td>{{ $user->name }}</td>

            <td>{{ $user->username }}</td>

            <td>

                @if(strtolower($user->role) == 'admin')

                <span class="badge badge-danger">
                   ADMIN
                </span>

               @elseif(strtolower($user->role) == 'noc')

              <span class="badge badge-info">
                  NOC
              </span>

             @elseif(strtolower($user->role) == 'operator')

             <span class="badge badge-primary">
                 OPERATOR
             </span>

           @elseif(strtolower($user->role) == 'employee')

           <span class="badge badge-secondary">
              EMPLOYEE
           </span>

           @else

           <span class="badge badge-secondary">
              VIEWER
           </span>

          @endif

            </td>

            <td>

                @if($user->status)

                    <span class="badge badge-success">
                        ACTIVE
                    </span>

                @else

                    <span class="badge badge-danger">
                        DISABLED
                    </span>

                @endif

            </td>

            <td>

                {{ $user->zone ?? 'All Zones' }}

            </td>

            <td>

                @if(strtolower($user->role) == 'admin')
                    <span class="badge badge-dark">ALL (Admin)</span>
                @elseif($user->modulePermissions->isEmpty())
                    <span class="badge badge-light text-muted">None</span>
                @else
                    @foreach($user->modulePermissions->groupBy('module') as $moduleKey => $grants)
                        @php
                            $moduleLabel = config("modules.{$moduleKey}.label", $moduleKey);
                            $isFull = $grants->contains('submodule', '');
                        @endphp

                        @if($isFull)
                            <span class="badge badge-info">{{ $moduleLabel }}</span>
                        @else
                            <span class="badge badge-secondary"
                                  title="{{ $grants->map(fn($g) => config("modules.{$moduleKey}.submodules.{$g->submodule}", $g->submodule))->implode(', ') }}">
                                {{ $moduleLabel }} ({{ $grants->count() }})
                            </span>
                        @endif
                    @endforeach
                @endif

            </td>

            <td>

                <a href="{{ route('users.show',$user->id) }}"
                class="btn btn-info btn-sm">
                View
                </a>

                @if(strtolower(auth()->user()->role) == 'admin')

                <a href="{{ route('users.edit',$user->id) }}"
                class="btn btn-warning btn-sm">
                Edit
                </a>

                @endif

                @if($user->id != auth()->id())

                <form action="{{ route('users.destroy',$user->id) }}"
                      method="POST"
                      style="display:inline;"
                      class="js-confirm-delete"
                      data-confirm-message="Delete user {{ $user->name }}?">

                    @csrf
                    @method('DELETE')

                    <button type="submit"
                            class="btn btn-danger btn-sm">

                        Delete

                    </button>

                </form>

                @endif

            </td>

        </tr>

        @endforeach

        </tbody>

    </table>

</div>

</div>

@stop

