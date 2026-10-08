<x-layouts.auth title="Set new password">
    <h1 class="font-display text-3xl font-semibold">Choose a new password</h1>

    <form method="POST" action="{{ route('password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="input">
        </div>
        <div>
            <label for="password" class="label">New password</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
            <p class="hint">At least 8 characters with letters and numbers.</p>
        </div>
        <div>
            <label for="password_confirmation" class="label">Confirm new password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
        </div>
        <button class="btn-primary w-full py-2.5">Reset password</button>
    </form>
</x-layouts.auth>
