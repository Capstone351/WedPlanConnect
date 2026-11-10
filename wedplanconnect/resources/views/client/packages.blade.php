<x-layouts.app title="Wedding Package" heading="Choose Your Wedding Package"
    subheading="Every package includes the same inclusions. They differ in the number of guests and cake layers.">
    @if ($booking)
        <div class="mb-6 rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-stone-700">
            Your booking has been made with <strong>{{ $booking->package }}</strong>. To change your package, please contact your planner.
        </div>
    @elseif (! $hasDate)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-stone-700">
            <span>Set your wedding date first, then choose your package.</span>
            <a href="{{ route('client.wedding-date') }}" class="btn-primary btn-sm">Set my wedding date</a>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        @foreach ($packages as $name => $package)
            @php $isChosen = ($booking?->package ?? $chosen) === $name; @endphp
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
                    @elseif (! $booking && $hasDate)
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

    @if (! $booking && $hasDate)
        <div class="card mt-6 flex flex-wrap items-center justify-between gap-4 p-5">
            <div>
                <h2 class="font-display text-lg font-semibold">Is your budget not in these packages?</h2>
                <p class="text-sm text-stone-600">Book an in-person meeting with your planner, and they'll discuss your budget with you.</p>
            </div>
            <a href="{{ $meetingUrl }}" class="btn-gold">Book a meeting</a>
        </div>
    @endif
</x-layouts.app>
