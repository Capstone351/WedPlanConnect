<x-layouts.guest title="Wedding Planning Management" :chatbot="true">
    {{-- Hero --}}
    <section class="lattice relative overflow-hidden bg-brand-950 text-white">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-brand-800/80 via-brand-900/90 to-brand-950"></div>
        <div class="pointer-events-none absolute -top-40 left-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-gold-400/15 blur-3xl"></div>

        <header class="relative mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3">
                <span class="grid size-10 place-items-center rounded-full bg-gradient-to-b from-gold-200 to-gold-500 text-brand-900 ring-4 ring-gold-400/15"><x-icon name="heart" class="size-5" /></span>
                <span class="font-display text-xl font-semibold tracking-wide">WedPlanConnect</span>
            </a>
            <nav class="flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-gold">Go to dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn border border-gold-300/40 text-gold-100 hover:bg-white/10">Log in</a>
                    <a href="{{ route('register') }}" class="btn-gold">Get Started</a>
                @endauth
            </nav>
        </header>

        <div class="relative mx-auto max-w-4xl px-4 pt-14 pb-24 text-center sm:px-6 sm:pt-20 sm:pb-32">
            <div class="eyebrow text-gold-300 sm:text-sm">Forever begins here</div>
            <h1 class="mt-4 font-display text-5xl leading-[1.08] font-semibold tracking-tight text-white sm:text-7xl">
                Every wedding detail,<br><span class="text-gold-gradient">beautifully in one place.</span>
            </h1>
            <x-ornament class="mx-auto mt-8 w-64" :light="true" />
            <p class="mx-auto mt-8 max-w-2xl text-lg text-brand-100/85">
                {{ config('wedplan.business_name') }} brings bookings, suppliers, décor, and payments together, and gives every couple a secure, real-time view of their wedding preparations.
            </p>
            <div class="mt-10 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="btn-gold px-7 py-3 text-base">Create a client account</a>
                <a href="{{ route('login') }}" class="btn border border-gold-300/40 px-7 py-3 text-base text-gold-100 hover:bg-white/10">Staff & vendor login</a>
            </div>
            <p class="mt-8 text-sm text-brand-100/70">Received a QR code on your contract? Scan it with your phone camera to view your wedding status.</p>
        </div>
        <div class="relative h-px bg-gradient-to-r from-transparent via-gold-400/70 to-transparent"></div>
    </section>

    {{-- How it works --}}
    <section class="bg-canvas">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <div class="text-center">
                <div class="eyebrow text-gold-600">Your journey</div>
                <h2 class="mt-1 font-display text-4xl font-semibold">How it works for couples</h2>
                <x-ornament class="mx-auto mt-4 w-48" />
            </div>
            <ol class="mt-14 grid gap-6 md:grid-cols-4">
                @foreach ([
                    ['I', 'Book a consultation', 'Your Wedding Planner checks your date and venue and creates your booking.'],
                    ['II', 'Choose your favorites', 'Browse the supplier catalog and their offerings, and mark the ones you love.'],
                    ['III', 'Receive your QR code', 'Once your booking is confirmed, your contract carries a personal QR code.'],
                    ['IV', 'Watch it come together', 'Scan the code, enter your one-time PIN, and follow your preparations live.'],
                ] as [$numeral, $step, $text])
                    <li class="card card-hover relative p-6 pt-10 text-center">
                        <span class="absolute -top-6 left-1/2 grid size-12 -translate-x-1/2 place-items-center rounded-full bg-gradient-to-b from-brand-700 to-brand-900 font-display text-lg font-semibold text-gold-200 ring-4 ring-canvas">{{ $numeral }}</span>
                        <h3 class="font-display text-xl font-semibold">{{ $step }}</h3>
                        <p class="mt-2 text-sm text-stone-600">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Features --}}
    <section class="border-y border-line bg-white">
        <div class="mx-auto grid max-w-7xl gap-px bg-line px-0 md:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['calendar', 'Conflict-free bookings', 'Real-time double-booking detection by event date and venue.'],
                ['store', 'Curated suppliers', $supplierCount
                    ? "Browse {$supplierCount} trusted ".str('supplier')->plural($supplierCount)." across {$categoryCount} ".str('category')->plural($categoryCount).'.'
                    : 'Browse our trusted suppliers and their offerings.'],
                ['qr', 'Private status portal', 'Your QR code plus a one-time PIN keeps your wedding details yours alone.'],
                ['chat', 'WedBot concierge', 'Instant answers to common questions, any time of day.'],
            ] as [$icon, $title, $text])
                <div class="bg-white p-8 text-center">
                    <div class="mx-auto grid size-14 place-items-center rounded-full bg-gradient-to-br from-gold-50 to-gold-100 text-gold-600 ring-1 ring-gold-300/70"><x-icon :name="$icon" class="size-6" /></div>
                    <h3 class="mt-4 font-display text-lg font-semibold">{{ $title }}</h3>
                    <p class="mt-1 text-sm text-stone-600">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <footer class="bg-brand-950 py-10 text-center">
        <div class="font-display text-lg text-gold-200">{{ config('wedplan.business_name') }}</div>
        <x-ornament class="mx-auto mt-3 w-40" :light="true" />
        <p class="mt-3 text-xs text-brand-100/60">© {{ date('Y') }} · {{ config('wedplan.business_address') }} · Personal data is processed in accordance with the Data Privacy Act of 2012 (RA 10173).</p>
    </footer>
</x-layouts.guest>
