<p class="text-muted">
    Update your account's profile information and email address.
</p>

<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <div class="form-group">
        <label>Name</label>
        <input id="name" name="name" type="text" class="form-control"
               value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
        @error('name')
            <div class="text-danger">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label>Email</label>
        <input id="email" name="email" type="email" class="form-control"
               value="{{ old('email', $user->email) }}" required autocomplete="username">
        @error('email')
            <div class="text-danger">{{ $message }}</div>
        @enderror

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <p class="text-muted mt-2">
                Your email address is unverified.
                <button form="send-verification" type="submit" class="btn btn-link p-0 align-baseline">
                    Click here to re-send the verification email.
                </button>
            </p>

            @if (session('status') === 'verification-link-sent')
                <div class="text-success">
                    A new verification link has been sent to your email address.
                </div>
            @endif
        @endif
    </div>

    <button type="submit" class="btn btn-primary">Save</button>

    @if (session('status') === 'profile-updated')
        <span class="text-success ml-2">Saved.</span>
    @endif
</form>
