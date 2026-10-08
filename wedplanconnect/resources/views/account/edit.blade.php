<x-layouts.app title="My Account" heading="My Account" subheading="Manage your sign-in credentials.">
    <div class="grid gap-6 lg:grid-cols-3 lg:items-start">
        <div class="card card-body">
            <div class="text-xs tracking-wide text-stone-500 uppercase">Signed in as</div>
            <div class="mt-1 text-lg font-semibold">{{ $user->name }}</div>
            <div class="text-sm text-stone-600">{{ $user->email }}</div>
            <div class="mt-3"><span class="badge-brand">{{ $user->roleLabel() }}</span></div>
            @if ($user->last_login_at)
                <p class="mt-4 text-xs text-stone-500">Last login: {{ $user->last_login_at->format('M j, Y g:i A') }}</p>
            @endif
        </div>

        <form method="POST" action="{{ route('account.password') }}" class="card lg:col-span-2">
            @csrf @method('PUT')
            <div class="card-header"><h2 class="card-title">Change password</h2></div>
            <div class="card-body space-y-4">
                <div>
                    <label for="current_password" class="label">Current password</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="input">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="label">New password</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
                        <p class="hint">At least 8 characters with letters and numbers.</p>
                    </div>
                    <div>
                        <label for="password_confirmation" class="label">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
                    </div>
                </div>
                <button class="btn-primary">Update password</button>
            </div>
        </form>
    </div>
</x-layouts.app>
