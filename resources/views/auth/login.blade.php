<x-guest-layout>

    <div class="auth-heading">
        <h2>Sign in</h2>
        <p>Welcome back — use your panel username and password.</p>
    </div>

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="auth-form" id="login-form">
        @csrf

        <!-- Username -->
        <div class="auth-field">
            <label for="username" class="auth-label">Username</label>

            <div class="auth-input-wrap @error('username') is-invalid @enderror">
                <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                <input
                    id="username"
                    class="auth-input"
                    type="text"
                    name="username"
                    value="{{ old('username') }}"
                    placeholder="Enter your username"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <x-input-error :messages="$errors->get('username')" class="auth-error" />
        </div>

        <!-- Password -->
        <div class="auth-field">
            <label for="password" class="auth-label">Password</label>

            <div class="auth-input-wrap @error('password') is-invalid @enderror">
                <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                <input
                    id="password"
                    class="auth-input"
                    type="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >
                <button type="button" class="auth-toggle" id="password-toggle" aria-label="Show password" aria-pressed="false">
                    <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3l18 18M10.6 5.1A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                </button>
            </div>

            <p class="auth-hint" id="caps-warning" hidden>Caps Lock is on</p>

            <x-input-error :messages="$errors->get('password')" class="auth-error" />
        </div>

        <!-- Remember -->
        <div class="auth-row">
            <label for="remember_me" class="auth-check">
                <input id="remember_me" type="checkbox" name="remember">
                <span>Remember me</span>
            </label>

            {{-- Only when mail is set up — with the "log" mailer the reset email never arrives. --}}
            @if (Route::has('password.request') && ! in_array(config('mail.default'), ['log', 'array'], true))
                <a href="{{ route('password.request') }}" class="auth-link">Forgot password?</a>
            @endif
        </div>

        <!-- Button -->
        <button type="submit" class="auth-submit" id="login-submit">
            <span class="auth-submit-label">Sign in</span>
            <svg class="auth-submit-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            <span class="auth-spinner" aria-hidden="true"></span>
        </button>

    </form>

    <p class="auth-note">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>
        <span>Sign-ins are recorded. Can't get in? Ask your administrator to reset your password.</span>
    </p>

    <script>
        (function () {
            var password = document.getElementById('password');
            var toggle = document.getElementById('password-toggle');
            var caps = document.getElementById('caps-warning');
            var form = document.getElementById('login-form');
            var submit = document.getElementById('login-submit');

            toggle.addEventListener('click', function () {
                var show = password.type === 'password';
                password.type = show ? 'text' : 'password';
                toggle.classList.toggle('is-visible', show);
                toggle.setAttribute('aria-pressed', show);
                toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
                password.focus();
            });

            ['keydown', 'keyup'].forEach(function (evt) {
                password.addEventListener(evt, function (e) {
                    if (e.getModifierState) caps.hidden = !e.getModifierState('CapsLock');
                });
            });
            password.addEventListener('blur', function () { caps.hidden = true; });

            form.addEventListener('submit', function () {
                submit.classList.add('is-loading');
                submit.disabled = true;
            });
        })();
    </script>

</x-guest-layout>
