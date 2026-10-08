<x-layouts.auth title="Log in">
    <div class="eyebrow text-gold-600">Welcome back</div>
    <h1 class="mt-2 font-display text-4xl font-semibold tracking-tight">Sign in</h1>
    <x-ornament class="mt-3 w-36 [&>span:first-child]:hidden" />
    <p class="mt-3 text-sm text-stone-500">Log in to your WedPlanConnect account.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label for="email" class="label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" @class(['input', 'input-error' => $errors->has('email')])>
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label for="password" class="label">Password</label>
                <a href="{{ route('password.request') }}" class="mb-1 text-xs link">Forgot password?</a>
            </div>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
        </div>
        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="checkbox" name="remember" class="rounded border-stone-300 text-brand-600"> Keep me signed in on this device
        </label>
        <button class="btn-primary w-full py-2.5">Log in</button>
    </form>

    <p class="mt-8 text-center text-sm text-stone-500">
        Engaged couple without an account? <a href="{{ route('register') }}" class="link">Register here</a>
    </p>
</x-layouts.auth>
