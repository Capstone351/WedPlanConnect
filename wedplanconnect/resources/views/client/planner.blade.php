<x-layouts.app title="My Planner" heading="Choose Your Wedding Planner"
    subheading="Pick the planner you'd like to work with. They'll be assigned to your wedding when your booking is created.">
    @if ($booking)
        <div class="hero-panel mb-6">
            <div class="relative flex flex-wrap items-center gap-5 p-6">
                <span class="grid size-14 place-items-center rounded-full bg-gradient-to-b from-gold-200 to-gold-500 font-display text-lg font-semibold text-brand-900">
                    {{ collect(explode(' ', $booking->planner->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('') }}
                </span>
                <div>
                    <div class="eyebrow text-gold-300">Your Wedding Planner</div>
                    <div class="mt-1 font-display text-2xl font-semibold text-white">{{ $booking->planner->name }}</div>
                    <p class="text-sm text-brand-100/80">Handling your wedding on {{ $booking->event_date->format('F j, Y') }}. To change planners, please contact {{ config('wedplan.business_name') }}.</p>
                </div>
            </div>
        </div>
    @endif

    @unless ($booking)
        @if (! $weddingDate)
            <div class="mb-6 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-gold-700">
                <span>Set your wedding date first so we can show which planners are free that day.</span>
                <a href="{{ route('client.wedding-date') }}" class="font-medium underline">Set my wedding date</a>
            </div>
        @elseif ($bookedIds->isNotEmpty())
            <div class="mb-6 rounded-xl border border-line bg-white px-4 py-3 text-sm text-stone-600">
                Showing availability for your wedding on <strong>{{ $weddingDate->format('l, F j, Y') }}</strong>. Planners who already have a wedding that day can't be chosen.
            </div>
        @endif
    @endunless

    <form method="POST" action="{{ route('client.planner.update') }}">
        @csrf @method('PUT')
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($planners as $planner)
                @php
                    $selected = (int) old('planner_id', $booking?->planner_id ?? $chosenId) === $planner->id;
                    $bookedThatDay = $bookedIds->contains($planner->id);
                    $initials = collect(explode(' ', $planner->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('');
                @endphp
                <label @class([
                    'card relative flex flex-col p-6 transition',
                    'cursor-pointer card-hover' => ! $booking && ! $bookedThatDay,
                    'opacity-60' => ($booking && ! $selected) || $bookedThatDay,
                    'ring-2 ring-gold-400 border-gold-300' => $selected,
                ])>
                    <input type="radio" name="planner_id" value="{{ $planner->id }}" class="peer sr-only" @checked($selected && ! $bookedThatDay) @disabled($booking || $bookedThatDay)>
                    @if ($bookedThatDay)
                        <span class="badge-gray absolute top-4 right-4">Booked on your date</span>
                    @elseif ($selected)
                        <span class="badge-gold absolute top-4 right-4">✓ {{ $booking ? 'Your planner' : 'Your choice' }}</span>
                    @endif
                    <span class="grid size-16 place-items-center rounded-full bg-gradient-to-b from-brand-700 to-brand-900 font-display text-xl font-semibold text-gold-200 ring-4 ring-gold-100">{{ $initials }}</span>
                    <span class="mt-4 font-display text-2xl font-semibold">{{ $planner->name }}</span>
                    <span class="eyebrow mt-1 text-[10px] text-gold-700">Wedding Planner</span>
                    <span class="mt-3 flex-1 text-sm text-stone-600">{{ $planner->bio ?: 'A '.config('wedplan.business_name').' Wedding Planner who will guide you from booking to your big day.' }}</span>
                    <span class="mt-4 flex gap-4 border-t border-line pt-3 text-xs text-stone-500">
                        <span><strong class="text-ink">{{ $planner->weddings_done }}</strong> {{ str('wedding')->plural($planner->weddings_done) }} completed</span>
                        <span><strong class="text-ink">{{ $planner->weddings_upcoming }}</strong> upcoming</span>
                    </span>
                    @if ($bookedThatDay)
                        <span class="mt-4 rounded-lg bg-stone-100 px-3 py-2 text-center text-sm text-stone-500">Unavailable on {{ $weddingDate->format('M j, Y') }}</span>
                    @elseif (! $booking)
                        <span class="mt-4 btn-secondary peer-checked:border-gold-400 peer-checked:bg-gold-50 peer-checked:text-gold-700">{{ $selected ? 'Selected' : 'Select this planner' }}</span>
                    @endif
                </label>
            @empty
                <div class="card col-span-full p-10 text-center text-stone-500">No planners are available yet. Please check back soon.</div>
            @endforelse
        </div>

        @if (! $booking && $planners->isNotEmpty())
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button class="btn-primary px-6 py-2.5">Save my choice</button>
                <span class="text-sm text-stone-500">You can change this anytime before your booking is created.</span>
            </div>
        @endif
    </form>
</x-layouts.app>
