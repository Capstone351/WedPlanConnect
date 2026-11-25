@php
    $type = old('account_type', $accountType);
    $types = [
        'client' => ['Couple', 'Plan our wedding', 'heart'],
        'planner' => ['Wedding Planner', 'Work with FMT', 'clipboard'],
        'vendor' => ['Supplier', 'Offer my services', 'store'],
    ];
@endphp
<x-layouts.auth title="Create account">
    <h1 class="font-display text-3xl font-semibold">Create your account</h1>
    <p class="mt-1 text-sm text-stone-500">Join {{ config('wedplan.business_name') }} on WedPlanConnect.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4" data-signup-form>
        @csrf
        <fieldset>
            <legend class="label">I am signing up as a…</legend>
            <div class="grid grid-cols-3 gap-2">
                @foreach ($types as $key => [$label, $hint, $icon])
                    <label class="cursor-pointer">
                        <input type="radio" name="account_type" value="{{ $key }}" class="peer sr-only" data-account-type @checked($type === $key)>
                        <span class="flex h-full flex-col items-center gap-1 rounded-xl border border-line p-3 text-center transition peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-200 peer-focus-visible:ring-2 peer-focus-visible:ring-gold-400">
                            <x-icon :name="$icon" class="size-5 text-brand-600" />
                            <span class="text-sm font-semibold text-ink">{{ $label }}</span>
                            <span class="text-[11px] leading-tight text-stone-500">{{ $hint }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div data-account-only="planner vendor" @if ($type === 'client') hidden @endif
            class="flex items-start gap-2 rounded-xl border border-gold-300 bg-gold-50 px-3 py-2.5 text-sm text-stone-700">
            <x-icon name="lock" class="mt-0.5 size-4 shrink-0 text-gold-700" />
            <span>Wedding Planner and Supplier accounts are reviewed by the administrator. You can log in once yours is approved, and we'll email you when it is.</span>
        </div>

        <div>
            <label for="name" class="label">
                <span data-account-only="client" @if ($type !== 'client') hidden @endif>First contact person <span class="font-normal text-stone-400">(full name)</span></span>
                <span data-account-only="planner vendor" @if ($type === 'client') hidden @endif>Your full name</span>
            </label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="150" autocomplete="name" placeholder="e.g. Ana Santos"
                @class(['input', 'input-error' => $errors->has('name')])>
            @error('name')<p class="error-text">{{ $message }}</p>@else<p class="hint" data-account-only="client" @if ($type !== 'client') hidden @endif>The person your planner will contact first, and who manages this account.</p>@enderror
        </div>

        {{-- Couple --}}
        @php
            $nameFields = [
                'bride' => ["Bride's", 'Ana', 'Santos'],
                'groom' => ["Groom's", 'Miguel', 'Reyes'],
            ];
        @endphp
        <fieldset class="rounded-xl border border-line p-4" data-account-only="client" @if ($type !== 'client') hidden @endif>
            <legend class="px-1 text-sm font-semibold text-ink">The couple</legend>
            <div class="space-y-4">
                @foreach ($nameFields as $who => [$whose, $firstExample, $lastExample])
                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (['first' => $firstExample, 'last' => $lastExample] as $part => $example)
                            @php $field = "{$who}_{$part}_name"; @endphp
                            <div>
                                <label for="{{ $field }}" class="label">{{ $whose }} {{ $part }} name</label>
                                <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" required maxlength="60" placeholder="e.g. {{ $example }}"
                                    autocomplete="off" @disabled($type !== 'client') @class(['input', 'input-error' => $errors->has($field)])>
                                @error($field)<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <p class="hint">Shown on your wedding dashboard. You can change them later in My Account.</p>
        </fieldset>

        {{-- Supplier --}}
        <fieldset class="rounded-xl border border-line p-4" data-account-only="vendor" @if ($type !== 'vendor') hidden @endif>
            <legend class="px-1 text-sm font-semibold text-ink">Your business</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="business_name" class="label">Business name</label>
                    <input id="business_name" name="business_name" value="{{ old('business_name') }}" required maxlength="150" placeholder="e.g. Lorr Aljas Event Styling"
                        @disabled($type !== 'vendor') @class(['input', 'input-error' => $errors->has('business_name')])>
                    @error('business_name')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="business_category" class="label">What you offer</label>
                    <select id="business_category" name="business_category" required @disabled($type !== 'vendor') @class(['input', 'input-error' => $errors->has('business_category')])>
                        <option value="">Choose…</option>
                        @foreach (\App\Models\Supplier::CATEGORIES as $category)
                            <option value="{{ $category }}" @selected(old('business_category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                    @error('business_category')<p class="error-text">{{ $message }}</p>@enderror
                </div>
            </div>
            <p class="hint">Once approved, you can add your products, packages and prices.</p>
        </fieldset>

        {{-- Wedding Planner --}}
        <div data-account-only="planner" @if ($type !== 'planner') hidden @endif>
            <label for="bio" class="label">Short introduction <span class="font-normal text-stone-400">(optional)</span></label>
            <textarea id="bio" name="bio" rows="2" maxlength="500" @disabled($type !== 'planner') class="input"
                placeholder="Shown to couples when they choose their planner, e.g. 5 years styling garden weddings in Cebu.">{{ old('bio') }}</textarea>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="email" class="label">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email"
                    placeholder="yourname@example.com"
                    @class(['input', 'input-error' => $errors->has('email')])>
                @error('email')<p class="error-text">{{ $message }}</p>@else<p class="hint">One-time PINs and notices are sent here.</p>@enderror
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
                @error('password')<p class="error-text">{{ $message }}</p>@else<p class="hint">At least 8 characters with letters and numbers.</p>@enderror
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
        @error('privacy')<p class="error-text">{{ $message }}</p>@enderror
        <button class="btn-primary w-full py-2.5">Create account</button>
    </form>

    <p class="mt-8 text-center text-sm text-stone-500">Already registered? <a href="{{ route('login') }}" class="link">Log in</a></p>
</x-layouts.auth>
