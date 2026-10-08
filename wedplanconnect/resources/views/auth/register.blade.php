<x-layouts.auth title="Create account">
    <h1 class="font-display text-3xl font-semibold">Create your client account</h1>
    <p class="mt-1 text-sm text-stone-500">For engaged couples working with {{ config('wedplan.business_name') }}.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label for="name" class="label">Full name(s)</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="150" placeholder="e.g. Ana & Miguel Santos" class="input">
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="email" class="label">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email" class="input">
            </div>
            <div>
                <label for="phone" class="label">Mobile number <span class="font-normal text-stone-400">(optional)</span></label>
                <input id="phone" name="phone" value="{{ old('phone') }}" maxlength="20" placeholder="+639171234567" class="input">
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="input">
                <p class="hint">At least 8 characters with letters and numbers.</p>
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="input">
            </div>
        </div>
        <label class="flex items-start gap-2 text-sm text-stone-600">
            <input type="checkbox" name="privacy" value="1" @checked(old('privacy')) class="mt-0.5 rounded border-stone-300 text-brand-600">
            <span>I agree that {{ config('wedplan.business_name') }} may collect and process my personal data solely for wedding planning, in accordance with the Data Privacy Act of 2012 (RA 10173).</span>
        </label>
        <button class="btn-primary w-full py-2.5">Create account</button>
    </form>

    <p class="mt-8 text-center text-sm text-stone-500">Already registered? <a href="{{ route('login') }}" class="link">Log in</a></p>
</x-layouts.auth>
