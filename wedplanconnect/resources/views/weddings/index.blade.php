<x-layouts.guest title="Our Weddings" :chatbot="true">
    <section class="lattice relative overflow-hidden bg-brand-950 text-white">
        <div class="absolute inset-0 bg-gradient-to-b from-brand-800/80 via-brand-900/90 to-brand-950"></div>
        <div class="relative"><x-public-header :dark="true" /></div>
        <div class="relative mx-auto max-w-3xl px-4 pt-8 pb-16 text-center sm:px-6">
            <div class="eyebrow text-gold-300">Our portfolio</div>
            <h1 class="mt-3 font-display text-5xl font-semibold tracking-tight text-white">Our Weddings</h1>
            <x-ornament class="mx-auto mt-5 w-56" :light="true" />
            <p class="mt-5 text-brand-100/85">Celebrations designed and styled by {{ config('wedplan.business_name') }}, shared with our couples' permission.</p>
        </div>
        <div class="relative h-px bg-gradient-to-r from-transparent via-gold-400/70 to-transparent"></div>
    </section>

    <section class="bg-canvas">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
            @if ($entries->isEmpty())
                <div class="card mx-auto max-w-xl p-10 text-center">
                    <h2 class="font-display text-2xl font-semibold">Our gallery is coming soon</h2>
                    <p class="mt-2 text-stone-600">We're preparing photos of our recent celebrations. In the meantime, start planning yours with us.</p>
                    <a href="{{ route('register') }}" class="btn-primary mt-6">Create an account</a>
                </div>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($entries as $entry)
                        <a href="{{ route('weddings.show', $entry) }}" class="card card-hover group overflow-hidden">
                            <div class="relative overflow-hidden">
                                <x-wedding-cover :entry="$entry" class="aspect-[4/3] transition duration-500 group-hover:scale-105" />
                                @if ($entry->photos->count() > 1)
                                    <span class="absolute right-3 bottom-3 rounded-full bg-black/55 px-2.5 py-1 text-xs text-white backdrop-blur">{{ $entry->photos->count() }} photos</span>
                                @endif
                            </div>
                            <div class="p-5">
                                @if ($entry->theme)<div class="eyebrow text-[10px] text-gold-700">{{ $entry->theme }}</div>@endif
                                <h2 class="mt-1 font-display text-2xl font-semibold">{{ $entry->title }}</h2>
                                <p class="mt-1 text-sm text-stone-500">{{ $entry->event_date?->format('F Y') }}@if ($entry->event_date && $entry->venue) · @endif{{ $entry->venue }}</p>
                                @if ($entry->summary)<p class="mt-3 line-clamp-2 text-sm text-stone-600">{{ $entry->summary }}</p>@endif
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="mt-10">{{ $entries->links() }}</div>
            @endif
        </div>
    </section>

    <x-public-footer />
</x-layouts.guest>
