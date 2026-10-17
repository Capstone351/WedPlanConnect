{{-- Cover photo of a portfolio wedding, or a monogram placeholder until photos are added. --}}
@props(['entry', 'photo' => null, 'class' => 'aspect-[4/3]'])
@php $photo ??= $entry->cover(); @endphp
@if ($photo)
    <img src="{{ $photo->url() }}" alt="{{ $photo->caption ?: $entry->title.' wedding' }}" loading="lazy"
        {{ $attributes->merge(['class' => "$class w-full object-cover"]) }}>
@else
    <div {{ $attributes->merge(['class' => "$class lattice relative grid w-full place-items-center overflow-hidden bg-brand-900"]) }} aria-hidden="true">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-700/90 via-brand-900/95 to-brand-950"></div>
        <div class="absolute inset-3 rounded-[inherit] border border-gold-300/30"></div>
        <div class="relative text-center">
            <div class="font-display text-4xl font-semibold text-gold-gradient">{{ $entry->initials() }}</div>
            <x-ornament class="mx-auto mt-2 w-24" :light="true" />
        </div>
    </div>
@endif
