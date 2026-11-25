@php $setup ??= false; @endphp
<x-layouts.auth :title="$setup ? 'Set your password' : 'Set new password'">
    <h1 class="font-display text-3xl font-semibold">{{ $setup ? 'Welcome! Choose your password' : 'Choose a new password' }}</h1>
    @if ($setup)
        <p class="mt-1 text-sm text-stone-500">Your account is ready. Pick a password, then log in with your email address.</p>
    @endif

    <form method="POST" action="{{ $setup ? route('password.setup.store') : route('password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required class="input">
        </div>
        <div>
            <label for="password" class="label">{{ $setup ? 'Password' : 'New password' }}</label>
            <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
            <p class="hint">At least 8 characters with letters and numbers.</p>
        </div>
        <div>
            <label for="password_confirmation" class="label">{{ $setup ? 'Confirm password' : 'Confirm new password' }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
        </div>
        <button class="btn-primary w-full py-2.5">{{ $setup ? 'Set my password' : 'Reset password' }}</button>
    </form>
</x-layouts.auth>
