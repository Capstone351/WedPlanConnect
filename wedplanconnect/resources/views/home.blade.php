@php
    $img = fn (string $file) => asset('images/fmt/'.$file);
    $slides = [
        ['reception.jpg', 'A candlelit reception with crystal chandeliers and white florals'],
        ['floral-arch.jpg', 'A floral arch entrance with glowing butterflies and a red carpet'],
        ['ceremony-aisle.jpg', 'A ceremony aisle lined with white flowers'],
        ['garden-ceremony.jpg', 'A garden ceremony with a floral arch'],
    ];
    $gallery = [
        ['reception.jpg', 'Reception styling with chandeliers and fresh florals'],
        ['floral-arch.jpg', 'Butterfly-lit floral arch entrance'],
        ['bouquet.jpg', 'Bridal bouquet of ivory roses and statice'],
        ['ceremony-aisle.jpg', 'Ceremony aisle with white floral arrangements'],
        ['gown.jpg', 'The bridal gown and veil'],
        ['garden-ceremony.jpg', 'Intimate garden ceremony set-up'],
        ['bridal-car.jpg', 'Vintage bridal car with floral décor'],
    ];
    $brand = config('wedplan.business_full_name');
@endphp
<x-layouts.guest :title="$brand.' · Weddings & Events'" :chatbot="true">
    {{-- Hero slideshow --}}
    <section class="relative isolate flex min-h-[100svh] flex-col overflow-hidden bg-brand-950 text-white" data-slideshow>
        @foreach ($slides as $i => [$file, $alt])
            <img src="{{ $img($file) }}" alt="{{ $alt }}" @if ($i > 0) loading="lazy" @endif
                class="hero-slide absolute inset-0 -z-20 h-full w-full object-cover transition-opacity duration-[1500ms] {{ $i === 0 ? 'opacity-100' : 'opacity-0' }}" data-slide>
        @endforeach
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-950/75 via-brand-950/45 to-brand-950/90"></div>

        <x-public-header :dark="true" />

        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col items-center justify-center px-4 pb-10 text-center sm:px-6">
            <div class="eyebrow text-gold-300">Weddings &amp; Events · {{ config('wedplan.business_address') }}</div>
            <h1 class="mt-5 font-display text-5xl leading-[1.05] font-semibold tracking-tight text-white drop-shadow sm:text-7xl">
                {{ $brand }}
            </h1>
            <x-ornament class="mx-auto mt-6 w-64" :light="true" />
            <p class="mx-auto mt-6 max-w-2xl text-lg text-brand-50/95 drop-shadow">
                Wedding planning, on-the-day coordination, and events hosting, styled with grace and care from the first consultation to the last dance.
            </p>
            <div class="mt-9 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="btn-gold px-7 py-3 text-base">Plan your wedding with us</a>
                <a href="#gallery" class="btn border border-gold-300/60 bg-black/20 px-7 py-3 text-base text-gold-100 backdrop-blur hover:bg-white/10">View our work</a>
            </div>
        </div>

        <div class="mb-8 flex justify-center gap-2" aria-hidden="true">
            @foreach ($slides as $i => $s)
                <span class="h-1.5 rounded-full transition-all {{ $i === 0 ? 'w-8 bg-gold-300' : 'w-3 bg-white/40' }}" data-slide-dot></span>
            @endforeach
        </div>
        <div class="h-px bg-gradient-to-r from-transparent via-gold-400/70 to-transparent"></div>
    </section>

    {{-- Welcome --}}
    <section class="bg-canvas">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-20 sm:px-6 lg:grid-cols-2">
            <div class="relative">
                <img src="{{ $img('bouquet.jpg') }}" alt="Bridal bouquet of ivory roses" loading="lazy" class="aspect-[4/5] w-full rounded-2xl object-cover shadow-[0_24px_50px_-30px_rgba(44,16,25,0.8)]">
                <img src="{{ $img('gown.jpg') }}" alt="Bridal gown and veil" loading="lazy" class="absolute -right-4 -bottom-8 hidden aspect-[3/4] w-40 rounded-xl border-4 border-canvas object-cover shadow-xl sm:block lg:-right-10 lg:w-48">
            </div>
            <div>
                <div class="eyebrow text-gold-600">Welcome</div>
                <h2 class="mt-3 font-display text-4xl leading-tight font-semibold sm:text-5xl">Your day, beautifully planned</h2>
                <x-ornament class="mt-4 w-40 [&>span:first-child]:hidden" />
                <p class="mt-5 text-stone-600">
                    {{ $brand }} ({{ config('wedplan.business_name') }}) is a Cebu-based wedding and events team. We plan, style, and coordinate every detail, from the bouquet and the gown to the bridal car and the reception, so you can simply enjoy the day.
                </p>
                <p class="mt-3 text-stone-600">
                    With WedPlanConnect, you can choose your planner, set your date and budget, pick favorite suppliers, and follow your preparations in real time.
                </p>
                <div class="mt-7 flex flex-wrap gap-3">
                    <a href="#services" class="btn-primary">Our services</a>
                    <a href="#contact" class="btn-secondary">Contact us</a>
                </div>
            </div>
        </div>
    </section>

    {{-- Services (from the business card) --}}
    <section id="services" class="scroll-mt-4 border-t border-line bg-white">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
            <div class="text-center">
                <div class="eyebrow text-gold-600">What we do</div>
                <h2 class="mt-2 font-display text-4xl font-semibold sm:text-5xl">Our services</h2>
                <x-ornament class="mx-auto mt-4 w-48" />
            </div>
            <div class="mt-12 grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['ceremony-aisle.jpg', 'Wedding Planning & Coordination', 'From your first consultation to the wedding day: venue, suppliers, décor, timeline, and budget, all planned together with you.'],
                    ['garden-ceremony.jpg', 'On-the-Day Coordination', 'Already planned everything? We take over on the day so the ceremony and reception run smoothly and on time.'],
                    ['reception.jpg', 'Events Hosting', 'Weddings, birthdays, and parties, hosted and styled with the same care and attention to detail.'],
                ] as [$file, $title, $text])
                    <div class="card card-hover group overflow-hidden">
                        <div class="overflow-hidden">
                            <img src="{{ $img($file) }}" alt="{{ $title }}" loading="lazy" class="aspect-[4/3] w-full object-cover transition duration-700 group-hover:scale-105">
                        </div>
                        <div class="p-6">
                            <h3 class="font-display text-2xl font-semibold">{{ $title }}</h3>
                            <p class="mt-2 text-sm text-stone-600">{{ $text }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Details band --}}
    <section class="lattice relative overflow-hidden bg-brand-950 text-white">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-800/85 via-brand-900/90 to-brand-950"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-20 sm:px-6">
            <div class="text-center">
                <div class="eyebrow text-gold-300">Every detail, cared for</div>
                <h2 class="mt-2 font-display text-4xl font-semibold text-white">The little things that make the day</h2>
                <x-ornament class="mx-auto mt-4 w-48" :light="true" />
            </div>
            <div class="mt-12 grid gap-6 sm:grid-cols-3">
                @foreach ([
                    ['bouquet.jpg', 'Bridal florals', 'Bouquets and arrangements in your colors.'],
                    ['gown.jpg', 'The bridal look', 'Gown, veil, and styling coordination.'],
                    ['bridal-car.jpg', 'Bridal car', 'Classic cars dressed in fresh flowers.'],
                ] as [$file, $title, $text])
                    <figure class="group overflow-hidden rounded-2xl ring-1 ring-gold-400/30">
                        <div class="overflow-hidden">
                            <img src="{{ $img($file) }}" alt="{{ $title }}" loading="lazy" class="aspect-[3/4] w-full object-cover transition duration-700 group-hover:scale-105">
                        </div>
                        <figcaption class="bg-black/25 p-5 text-center">
                            <div class="font-display text-xl font-semibold text-gold-100">{{ $title }}</div>
                            <div class="mt-1 text-sm text-brand-100/80">{{ $text }}</div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Gallery --}}
    <section id="gallery" class="scroll-mt-4 bg-canvas">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
            <div class="text-center">
                <div class="eyebrow text-gold-600">Gallery</div>
                <h2 class="mt-2 font-display text-4xl font-semibold sm:text-5xl">Moments we've styled</h2>
                <x-ornament class="mx-auto mt-4 w-48" />
                <p class="mx-auto mt-4 max-w-2xl text-stone-600">A glimpse of the ceremonies, receptions, and details we've prepared for our couples.</p>
            </div>
            <div class="mt-12 columns-1 gap-4 sm:columns-2 lg:columns-3">
                @foreach ($gallery as $i => [$file, $caption])
                    <button type="button" class="group relative mb-4 block w-full break-inside-avoid overflow-hidden rounded-xl"
                        data-gallery-index="{{ $i }}" data-src="{{ $img($file) }}" data-caption="{{ $caption }}" aria-label="Open photo: {{ $caption }}">
                        <img src="{{ $img($file) }}" alt="{{ $caption }}" loading="lazy" class="w-full transition duration-700 group-hover:scale-105">
                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 pt-10 pb-3 text-left text-sm text-white opacity-0 transition group-hover:opacity-100">{{ $caption }}</span>
                    </button>
                @endforeach
            </div>
            @if ($weddingCount)
                <div class="mt-8 text-center">
                    <a href="{{ route('weddings.index') }}" class="btn-secondary px-6 py-3">See our weddings →</a>
                </div>
            @endif
        </div>
    </section>

    {{-- Lightbox for the gallery --}}
    <div id="lightbox" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" aria-label="Photo viewer">
        <button type="button" data-lightbox-close class="absolute top-4 right-4 rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white" aria-label="Close"><x-icon name="x" class="size-7" /></button>
        <button type="button" data-lightbox-prev class="absolute left-2 rounded-full p-3 text-3xl text-white/80 hover:bg-white/10 sm:left-6" aria-label="Previous photo">‹</button>
        <figure class="max-h-full max-w-5xl text-center">
            <img data-lightbox-img src="" alt="" class="mx-auto max-h-[80vh] rounded-lg object-contain">
            <figcaption data-lightbox-caption class="mt-3 text-sm text-white/80"></figcaption>
        </figure>
        <button type="button" data-lightbox-next class="absolute right-2 rounded-full p-3 text-3xl text-white/80 hover:bg-white/10 sm:right-6" aria-label="Next photo">›</button>
    </div>

    {{-- Portfolio weddings with photos (managed under Portfolio) --}}
    @if ($weddings->isNotEmpty())
        <section id="our-weddings" class="scroll-mt-4 border-t border-line bg-white">
            <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6">
                <div class="text-center">
                    <div class="eyebrow text-gold-600">Our portfolio</div>
                    <h2 class="mt-2 font-display text-4xl font-semibold sm:text-5xl">Weddings we've styled</h2>
                    <x-ornament class="mx-auto mt-4 w-48" />
                </div>
                <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($weddings as $entry)
                        <a href="{{ route('weddings.show', $entry) }}" class="card card-hover group overflow-hidden">
                            <div class="overflow-hidden"><x-wedding-cover :entry="$entry" class="aspect-[4/3] transition duration-500 group-hover:scale-105" /></div>
                            <div class="p-5">
                                @if ($entry->theme)<div class="eyebrow text-[10px] text-gold-700">{{ $entry->theme }}</div>@endif
                                <h3 class="mt-1 font-display text-2xl font-semibold">{{ $entry->title }}</h3>
                                <p class="mt-1 text-sm text-stone-500">{{ $entry->event_date?->format('F Y') }}@if ($entry->event_date && $entry->venue) · @endif{{ $entry->venue }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- How it works --}}
    <section id="how-it-works" class="scroll-mt-4 border-t border-line bg-white">
        <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
            <div class="text-center">
                <div class="eyebrow text-gold-600">Your journey</div>
                <h2 class="mt-2 font-display text-4xl font-semibold">Planning with us is simple</h2>
                <x-ornament class="mx-auto mt-4 w-48" />
            </div>
            <ol class="mt-14 grid gap-6 md:grid-cols-4">
                @foreach ([
                    ['I', 'Create your account', 'Set your wedding date, budget, and favorite suppliers, and choose your planner.'],
                    ['II', 'Meet your planner', 'Your Wedding Planner confirms the date and venue and creates your booking.'],
                    ['III', 'Receive your QR code', 'Your contract carries a personal QR code for your wedding status page.'],
                    ['IV', 'Watch it come together', 'Scan the code, enter your one-time PIN, and follow your preparations live.'],
                ] as [$numeral, $step, $text])
                    <li class="card card-hover relative p-6 pt-10 text-center">
                        <span class="absolute -top-6 left-1/2 grid size-12 -translate-x-1/2 place-items-center rounded-full bg-gradient-to-b from-brand-700 to-brand-900 font-display text-lg font-semibold text-gold-200 ring-4 ring-white">{{ $numeral }}</span>
                        <h3 class="font-display text-xl font-semibold">{{ $step }}</h3>
                        <p class="mt-2 text-sm text-stone-600">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Contact --}}
    <section id="contact" class="relative isolate scroll-mt-4 overflow-hidden text-white">
        <img src="{{ $img('floral-arch.jpg') }}" alt="" loading="lazy" class="absolute inset-0 -z-20 h-full w-full object-cover">
        <div class="absolute inset-0 -z-10 bg-gradient-to-br from-brand-950/90 via-brand-900/85 to-brand-950/95"></div>
        <div class="mx-auto max-w-5xl px-4 py-20 text-center sm:px-6">
            <div class="eyebrow text-gold-300">Let's talk</div>
            <h2 class="mt-2 font-display text-4xl font-semibold text-white sm:text-5xl">Plan your celebration with us</h2>
            <x-ornament class="mx-auto mt-4 w-48" :light="true" />
            <p class="mx-auto mt-4 max-w-2xl text-brand-100/85">Call or message us for a consultation, or create an account to start planning online.</p>

            <div class="mt-10 grid gap-5 sm:grid-cols-2">
                @foreach (config('wedplan.contacts') as $contact)
                    <a href="tel:{{ preg_replace('/\D/', '', $contact['phone']) }}" class="group rounded-2xl border border-gold-300/30 bg-black/25 p-6 backdrop-blur transition hover:border-gold-300/70">
                        <span class="mx-auto grid size-12 place-items-center rounded-full bg-gradient-to-b from-gold-200 to-gold-500 text-brand-900">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                        </span>
                        <span class="mt-4 block font-display text-2xl font-semibold text-white">{{ $contact['name'] }}</span>
                        <span class="mt-1 block text-lg tracking-wide text-gold-200 group-hover:text-gold-100">{{ $contact['phone'] }}</span>
                        <span class="mt-1 block text-xs text-brand-100/70">Tap to call</span>
                    </a>
                @endforeach
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3 text-sm">
                <span class="text-brand-100/80">Find us on Facebook:</span>
                @foreach (config('wedplan.facebook_pages') as $page)
                    <a href="{{ $page['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full border border-gold-300/40 px-4 py-2 text-gold-100 hover:bg-white/10">
                        <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12a10 10 0 10-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0022 12z"/></svg>
                        {{ $page['label'] }}
                    </a>
                @endforeach
            </div>

            <p class="mt-10 text-sm text-brand-100/70">Received a QR code on your contract? Scan it with your phone camera to view your wedding status.</p>
        </div>
    </section>

    <x-public-footer />
</x-layouts.guest>
