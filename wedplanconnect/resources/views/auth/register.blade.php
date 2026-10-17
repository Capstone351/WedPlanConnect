<x-layouts.auth title="Create account">
    <h1 class="font-display text-3xl font-semibold">Create your account</h1>
    <p class="mt-1 text-sm text-stone-500">Plan your wedding with {{ config('wedplan.business_name') }}.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4">
        @csrf
        <div>
            <label for="name" class="label">Full name</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="150" autocomplete="name" placeholder="e.g. Ana Santos"
                @class(['input', 'input-error' => $errors->has('name')])>
            @error('name')<p class="error-text">{{ $message }}</p>@else<p class="hint">One person per account.</p>@enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="email" class="label">Gmail address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email"
                    placeholder="yourname@gmail.com" pattern="[A-Za-z0-9.+_\-]+@[Gg][Mm][Aa][Ii][Ll]\.[Cc][Oo][Mm]"
                    title="Use a Gmail address ending in @gmail.com"
                    @class(['input', 'input-error' => $errors->has('email')])>
                @error('email')<p class="error-text">{{ $message }}</p>@else<p class="hint">Must end with @gmail.com</p>@enderror
            </div>
            <div>
                <label for="phone" class="label">Mobile number</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required inputmode="numeric" maxlength="11" minlength="11"
                    pattern="09[0-9]{9}" autocomplete="tel" placeholder="09171234567" data-digits-only
                    title="Must start with 09 and have 11 digits, e.g. 09171234567"
                    @class(['input', 'input-error' => $errors->has('phone')])>
                @error('phone')<p class="error-text">{{ $message }}</p>@else<p class="hint">Starts with 09, 11 digits, e.g. 09171234567</p>@enderror
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
