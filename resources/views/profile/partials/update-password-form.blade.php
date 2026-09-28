<p class="text-muted">
    Ensure your account is using a long, random password to stay secure.
</p>

<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="form-group">
        <label>Current Password</label>
        <input id="update_password_current_password" name="current_password" type="password"
               class="form-control" autocomplete="current-password">
        @error('current_password', 'updatePassword')
            <div class="text-danger">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label>New Password</label>
        <input id="update_password_password" name="password" type="password"
               class="form-control" autocomplete="new-password">
        @error('password', 'updatePassword')
            <div class="text-danger">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label>Confirm Password</label>
        <input id="update_password_password_confirmation" name="password_confirmation" type="password"
               class="form-control" autocomplete="new-password">
        @error('password_confirmation', 'updatePassword')
            <div class="text-danger">{{ $message }}</div>
        @enderror
    </div>

    <button type="submit" class="btn btn-primary">Save</button>

    @if (session('status') === 'password-updated')
        <span class="text-success ml-2">Saved.</span>
    @endif
</form>
