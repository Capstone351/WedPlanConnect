@php
    $cover = $entry->photos->first();
    $gallery = $entry->photos;
@endphp
<x-layouts.guest :title="$entry->title.' · Our Weddings'" :chatbot="true">
    {{-- Cover --}}
    <section class="relative isolate flex min-h-[70vh] flex-col overflow-hidden bg-brand-950 text-white">
        @if ($cover)
            <img src="{{ $cover->url() }}" alt="{{ $cover->caption ?: $entry->title }}" class="absolute inset-0 -z-10 h-full w-full object-cover">
            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-950/70 via-brand-950/40 to-brand-950/95"></div>
        @else
            <div class="lattice absolute inset-0 -z-10"></div>
            <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-800/80 via-brand-900/90 to-brand-950"></div>
        @endif
        <x-public-header :dark="true" />
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col items-center justify-end px-4 pb-14 text-center sm:px-6">
            <a href="{{ route('weddings.index') }}" class="text-sm text-gold-200 hover:text-white">← All weddings</a>
            @if ($entry->theme)<div class="mt-6 eyebrow text-gold-300">{{ $entry->theme }}</div>@endif
            <h1 class="mt-3 font-display text-5xl font-semibold tracking-tight text-white sm:text-6xl">{{ $entry->title }}</h1>
            <x-ornament class="mx-auto mt-5 w-56" :light="true" />
            <p class="mt-4 font-display text-lg text-gold-100">
                {{ $entry->event_date?->format('F j, Y') }}@if ($entry->event_date && $entry->venue) · @endif{{ $entry->venue }}
            </p>
        </div>
        <div class="h-px bg-gradient-to-r from-transparent via-gold-400/70 to-transparent"></div>
    </section>

    {{-- Story --}}
    @if ($entry->summary || $entry->story)
        <section class="bg-canvas">
            <div class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6">
                <div class="eyebrow text-gold-600">Their story</div>
                @if ($entry->summary)
                    <p class="mt-4 font-display text-2xl leading-relaxed text-ink">{{ $entry->summary }}</p>
                @endif
                @if ($entry->story)
                    <div class="mt-6 space-y-4 text-left text-stone-700 sm:text-center">
                        @foreach (preg_split('/\R{2,}/', trim($entry->story)) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- Gallery --}}
    @if ($gallery->count() > 0)
        <section class="border-t border-line bg-white">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
                <div class="text-center">
                    <div class="eyebrow text-gold-600">Gallery</div>
                    <h2 class="mt-2 font-display text-3xl font-semibold">Moments from the day</h2>
                    <x-ornament class="mx-auto mt-4 w-40" />
                </div>
                <div class="mt-10 columns-1 gap-4 sm:columns-2 lg:columns-3" data-gallery>
                    @foreach ($gallery as $i => $photo)
                        <button type="button" class="group relative mb-4 block w-full break-inside-avoid overflow-hidden rounded-xl" data-gallery-index="{{ $i }}"
                            data-src="{{ $photo->url() }}" data-caption="{{ $photo->caption }}" aria-label="Open photo {{ $i + 1 }}">
                            <img src="{{ $photo->url() }}" alt="{{ $photo->caption ?: $entry->title.' – photo '.($i + 1) }}" loading="lazy" class="w-full transition duration-500 group-hover:scale-105">
                            @if ($photo->caption)
                                <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 pt-8 pb-3 text-left text-sm text-white opacity-0 transition group-hover:opacity-100">{{ $photo->caption }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Lightbox --}}
        <div id="lightbox" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/90 p-4" role="dialog" aria-modal="true" aria-label="Photo viewer">
            <button type="button" data-lightbox-close class="absolute top-4 right-4 rounded-full p-2 text-white/80 hover:bg-white/10 hover:text-white" aria-label="Close"><x-icon name="x" class="size-7" /></button>
            <button type="button" data-lightbox-prev class="absolute left-2 rounded-full p-3 text-3xl text-white/80 hover:bg-white/10 sm:left-6" aria-label="Previous photo">‹</button>
            <figure class="max-h-full max-w-5xl text-center">
                <img data-lightbox-img src="" alt="" class="mx-auto max-h-[80vh] rounded-lg object-contain">
                <figcaption data-lightbox-caption class="mt-3 text-sm text-white/80"></figcaption>
            </figure>
            <button type="button" data-lightbox-next class="absolute right-2 rounded-full p-3 text-3xl text-white/80 hover:bg-white/10 sm:right-6" aria-label="Next photo">›</button>
        </div>
    @endif

    {{-- Testimonial --}}
    @if ($entry->testimonial)
        <section class="lattice relative overflow-hidden bg-brand-900 text-white">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-700/80 to-brand-950"></div>
            <figure class="relative mx-auto max-w-3xl px-4 py-16 text-center sm:px-6">
                <div class="font-display text-6xl leading-none text-gold-300">“</div>
                <blockquote class="font-display text-2xl leading-relaxed text-white">{{ $entry->testimonial }}</blockquote>
                <x-ornament class="mx-auto mt-6 w-40" :light="true" />
                <figcaption class="mt-4 eyebrow text-gold-300">{{ $entry->testimonial_by ?: $entry->title }}</figcaption>
            </figure>
        </section>
    @endif

    {{-- More weddings --}}
    @if ($more->isNotEmpty())
        <section class="bg-canvas">
            <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
                <h2 class="text-center font-display text-3xl font-semibold">More weddings</h2>
                <x-ornament class="mx-auto mt-4 w-40" />
                <div class="mt-10 grid gap-6 sm:grid-cols-3">
                    @foreach ($more as $other)
                        <a href="{{ route('weddings.show', $other) }}" class="card card-hover group overflow-hidden">
                            <div class="overflow-hidden"><x-wedding-cover :entry="$other" class="aspect-[4/3] transition duration-500 group-hover:scale-105" /></div>
                            <div class="p-4">
                                <h3 class="font-display text-xl font-semibold">{{ $other->title }}</h3>
                                <p class="text-sm text-stone-500">{{ $other->event_date?->format('F Y') }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="border-t border-line bg-white py-14 text-center">
        <h2 class="font-display text-3xl font-semibold">Dreaming of a day like this?</h2>
        <p class="mt-2 text-stone-600">Let {{ config('wedplan.business_name') }} plan and style yours.</p>
        <a href="{{ route('register') }}" class="btn-primary mt-6 px-7 py-3 text-base">Plan your wedding with us</a>
    </section>

    <x-public-footer />
</x-layouts.guest>
