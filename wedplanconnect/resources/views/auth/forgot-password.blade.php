<x-layouts.auth title="Forgot password">
    <h1 class="font-display text-3xl font-semibold">Reset your password</h1>
    <p class="mt-1 text-sm text-stone-500">Enter your account email and we'll send you a secure, time-limited reset link.</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
        </div>
        <button class="btn-primary w-full py-2.5">Email reset link</button>
    </form>

    <p class="mt-8 text-center text-sm"><a href="{{ route('login') }}" class="link">Back to login</a></p>
</x-layouts.auth>
