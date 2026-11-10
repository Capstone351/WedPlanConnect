@php
    $agenda = 'Our budget is ₱'.number_format((float) $budget, 0).". We'd like to talk about a package that fits it.";
@endphp
<x-layouts.app title="Wedding Package" heading="Choose Your Wedding Package"
    subheading="Every package includes the same inclusions. They differ in the number of guests and cake layers.">
    @if ($booking)
        <div class="mb-6 rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-stone-700">
            Your booking has been made with <strong>{{ $booking->package }}</strong>. To change your package, please contact your planner.
        </div>
    @elseif ($budget === null)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-stone-700">
            <span>Set your budget first, so we can show which packages fit.</span>
            <a href="{{ route('client.budget') }}" class="btn-primary btn-sm">Set my budget</a>
        </div>
    @elseif ($belowAll)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-4 text-sm text-brand-900">
            <span>
                Your budget of <strong>₱{{ number_format($budget, 0) }}</strong> is below our packages.
                Book a meeting with your planner so they can help you plan a wedding that fits it.
            </span>
            <a href="{{ route('client.meetings', ['agenda' => $agenda]) }}" class="btn-gold">Book a meeting</a>
        </div>
    @else
        <div class="mb-6 rounded-xl bg-white px-4 py-3 text-sm ring-1 ring-line">
            Your budget: <strong class="tabular-nums">₱{{ number_format($budget, 0) }}</strong>
            · <a href="{{ route('client.budget') }}" class="link">Change</a>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        @foreach ($packages as $name => $package)
            @php
                $isChosen = ($booking?->package ?? $chosen) === $name;
                $over = $budget !== null && $package['price'] > $budget;
            @endphp
            <div @class(['card flex flex-col overflow-hidden', 'ring-2 ring-gold-400' => $isChosen])>
                <div class="hero-panel rounded-none">
                    <div class="relative px-6 py-6 text-center">
                        <div class="font-display text-2xl font-semibold text-white">{{ $name }}</div>
                        <div class="mt-1 font-display text-4xl font-semibold text-gold-gradient tabular-nums">₱{{ number_format($package['price'], 0) }}</div>
                        <div class="mt-2 text-sm text-gold-100">{{ $package['guests'] }} pax · Cake: {{ $package['cake_layers'] }} layers</div>
                    </div>
                </div>
                <ul class="flex-1 space-y-1.5 px-6 py-5 text-sm text-stone-700">
                    @foreach ($inclusions as $line)
                        <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" /> {{ $line }}</li>
                    @endforeach
                </ul>
                <div class="border-t border-line px-6 py-4 text-center">
                    @if ($isChosen)
                        <span class="badge-green">{{ $booking ? 'In your booking' : 'Your chosen package' }}</span>
                    @elseif ($booking || $budget === null)
                        {{-- Nothing to choose until a budget is set, or once booked. --}}
                    @elseif ($over)
                        <span class="badge-red">₱{{ number_format($package['price'] - $budget, 0) }} above your budget</span>
                    @else
                        <form method="POST" action="{{ route('client.package.update') }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="package" value="{{ $name }}">
                            <button class="btn-primary w-full">Choose {{ $name }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if (! $booking && $budget !== null && ! $belowAll)
        <p class="mt-6 text-center text-sm text-stone-500">
            Looking for something different? <a href="{{ route('client.meetings', ['agenda' => $agenda]) }}" class="link">Book a meeting with your planner</a>.
        </p>
    @endif
</x-layouts.app>
