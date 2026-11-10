<x-layouts.app title="Wedding Date" heading="Your Wedding Date"
    subheading="Tell your Wedding Planner when you'd like to get married, then send them your booking.">
    @if ($booking)
        <div class="hero-panel">
            <div class="relative flex flex-col items-center gap-2 px-6 py-10 text-center">
                <div class="eyebrow text-gold-300">{{ $booking->status === 'pending' ? 'Booking sent for' : 'Your wedding is booked for' }}</div>
                <div class="font-display text-4xl font-semibold text-white sm:text-5xl">{{ $booking->event_date->format('l, F j, Y') }}</div>
                <x-ornament class="mx-auto mt-2 w-48" :light="true" />
                <p class="mt-2 text-brand-100/85">{{ $booking->venue }} · {{ $booking->package }} · {{ max(0, $booking->daysToGo()) }} days to go</p>
                @if ($booking->status === 'pending')
                    <p class="mt-4 max-w-md text-sm text-brand-100/70">Waiting for <strong class="text-gold-200">{{ $booking->planner->name }}</strong> to set the price and confirm your booking.</p>
                @endif
                <p class="mt-4 max-w-md text-sm text-brand-100/70">Need a different date? Please contact your planner, <strong class="text-gold-200">{{ $booking->planner->name }}</strong>, so they can check availability.</p>
            </div>
        </div>
    @else
        <form method="POST" action="{{ route('client.wedding-date.update') }}" class="grid gap-6 lg:grid-cols-3 lg:items-start"
            data-date-form data-availability-url="{{ route('client.wedding-date.availability') }}" data-planner-name="{{ $user->preferredPlanner?->name }}">
            @csrf @method('PUT')

            <div class="card lg:col-span-2">
                <div class="card-header"><h2 class="card-title">Preferred date</h2></div>
                <div class="card-body grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="wedding_date" class="label">Wedding date</label>
                        <input id="wedding_date" name="wedding_date" type="date" required min="{{ today()->addDay()->toDateString() }}"
                            value="{{ old('wedding_date', $user->wedding_date?->toDateString()) }}" data-date-input
                            @class(['input text-base', 'input-error' => $errors->has('wedding_date')])>
                        @error('wedding_date')<p class="error-text">{{ $message }}</p>@enderror
                        <p class="mt-2 hidden rounded-lg px-3 py-2 text-xs" data-date-hint></p>
                    </div>
                    <div>
                        <label for="wedding_date_alt" class="label">Backup date <span class="font-normal text-stone-400">(optional)</span></label>
                        <input id="wedding_date_alt" name="wedding_date_alt" type="date" min="{{ today()->addDay()->toDateString() }}"
                            value="{{ old('wedding_date_alt', $user->wedding_date_alt?->toDateString()) }}"
                            @class(['input', 'input-error' => $errors->has('wedding_date_alt')])>
                        @error('wedding_date_alt')<p class="error-text">{{ $message }}</p>@else<p class="hint">In case your first choice isn't available.</p>@enderror
                    </div>
                    @if ($plannerBusyDates->isNotEmpty())
                        <div class="rounded-lg bg-stone-50 px-3 py-2.5 text-xs text-stone-600 ring-1 ring-line sm:col-span-2">
                            <span class="font-semibold text-ink">{{ $user->preferredPlanner->name }} is already booked on:</span>
                            {{ $plannerBusyDates->map(fn ($d) => $d->format('M j, Y'))->join(' · ') }}.
                            These dates can't be chosen while {{ $user->preferredPlanner->name }} is your planner.
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <label for="wedding_venue" class="label">Preferred venue <span class="font-normal text-stone-400">(optional)</span></label>
                        <input id="wedding_venue" name="wedding_venue" maxlength="200" value="{{ old('wedding_venue', $user->wedding_venue) }}"
                            placeholder="e.g. Casa Gorordo Museum, Cebu City (or leave blank if you're still deciding)" class="input" data-venue-input>
                        <p class="hint">Your planner checks the date and venue together to avoid double-bookings.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-4 lg:sticky lg:top-6">
                <div class="hero-panel">
                    <div class="relative p-6 text-center">
                        <div class="eyebrow text-gold-300">Your big day</div>
                        <div class="mt-2 font-display text-2xl font-semibold text-white" data-date-preview>
                            {{ $user->wedding_date ? $user->wedding_date->format('l, F j, Y') : 'Choose a date' }}
                        </div>
                        <div class="mt-1 text-sm text-gold-200" data-days-preview>
                            @if ($user->wedding_date){{ (int) today()->diffInDays($user->wedding_date) }} days to go @endif
                        </div>
                    </div>
                </div>
                <button class="btn-primary w-full py-3">Save wedding date</button>
                <p class="px-1 text-xs text-stone-500">
                    @if ($user->preferredPlanner)
                        Shared with your chosen planner, <strong>{{ $user->preferredPlanner->name }}</strong>.
                    @else
                        Shared with {{ config('wedplan.business_name') }}'s planners. You can also <a href="{{ route('client.planner') }}" class="link">choose your planner</a>.
                    @endif
                </p>
            </div>
        </form>

        @if ($user->wedding_date)
            <form method="POST" action="{{ route('client.booking-request') }}" class="card mt-6" id="send-booking"
                data-confirm="Send this booking to {{ $user->preferredPlanner?->name ?? 'your planner' }}? After this, date changes go through your planner.">
                @csrf
                <div class="card-header"><h2 class="card-title">Send your booking to your planner</h2></div>
                <div class="card-body space-y-5">
                    @if (! $user->preferredPlanner || $user->preferredPlanner->trashed())
                        <p class="text-sm text-stone-600">Choose your Wedding Planner first, then come back here to send them your booking.</p>
                        <a href="{{ route('client.planner') }}" class="btn-primary">Choose your planner</a>
                    @else
                        <p class="text-sm text-stone-600">
                            This sends <strong>{{ $user->wedding_date->format('l, F j, Y') }}</strong> to <strong>{{ $user->preferredPlanner->name }}</strong>'s bookings.
                            They'll confirm it.
                        </p>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <span class="label">Package</span>
                                @if ($user->wedding_package)
                                    <div class="flex items-center justify-between gap-2 rounded-lg bg-stone-50 px-3 py-2.5 text-sm ring-1 ring-line">
                                        <span><strong>{{ $user->wedding_package }}</strong> · ₱{{ number_format(\App\Models\Booking::packagePrice($user->wedding_package) ?? 0, 0) }}</span>
                                        <a href="{{ route('client.package') }}" class="link">Change</a>
                                    </div>
                                @else
                                    <a href="{{ route('client.package') }}" class="btn-secondary w-full">Choose your package first</a>
                                @endif
                            </div>
                            <div>
                                <label for="venue" class="label">Wedding venue</label>
                                <input id="venue" name="venue" required maxlength="200" value="{{ old('venue', $user->wedding_venue) }}"
                                    placeholder="e.g. Casa Gorordo Museum, Cebu City" @class(['input', 'input-error' => $errors->has('venue')])>
                                @error('venue')<p class="error-text">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <button class="btn-gold">Send booking to {{ $user->preferredPlanner->name }}</button>
                    @endif
                </div>
            </form>
        @endif
    @endif
</x-layouts.app>
