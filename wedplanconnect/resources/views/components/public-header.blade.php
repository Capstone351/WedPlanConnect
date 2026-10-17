{{-- Navigation bar for public pages. "dark" sits on top of a burgundy/photo hero. --}}
@props(['dark' => true])
@php
    $link = fn (bool $active = false) => [
        'rounded-lg px-3 py-2 font-medium transition',
        'text-gold-100 hover:bg-white/10' => $dark,
        'text-ink hover:bg-gold-50' => ! $dark,
        'underline decoration-gold-400 underline-offset-8' => $active,
    ];
@endphp
<header class="relative z-10 mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-6">
    <a href="{{ route('home') }}" class="flex items-center gap-3">
        <span class="grid size-10 place-items-center rounded-full bg-gradient-to-b from-gold-200 to-gold-500 text-brand-900 ring-4 ring-gold-400/15"><x-icon name="heart" class="size-5" /></span>
        <span class="leading-tight">
            <span @class(['block font-display text-xl font-semibold tracking-wide', 'text-white' => $dark, 'text-ink' => ! $dark])>{{ config('wedplan.business_full_name') }}</span>
            <span @class(['block text-[10px] tracking-[0.2em] uppercase', 'text-gold-300/90' => $dark, 'text-gold-700' => ! $dark])>Weddings &amp; Events</span>
        </span>
    </a>
    <nav class="flex flex-wrap items-center gap-1 text-sm sm:gap-2">
        <a href="{{ route('home') }}#services" @class(array_merge($link(), ['hidden md:inline-block']))>Services</a>
        <a href="{{ route('home') }}#gallery" @class(array_merge($link(), ['hidden md:inline-block']))>Gallery</a>
        <a href="{{ route('weddings.index') }}" @class($link(request()->routeIs('weddings.*')))>Our Weddings</a>
        <a href="{{ route('home') }}#contact" @class(array_merge($link(), ['hidden sm:inline-block']))>Contact</a>
        @auth
            <a href="{{ route('dashboard') }}" class="btn-gold ml-1">My dashboard</a>
        @else
            <a href="{{ route('login') }}" class="btn-gold ml-1">Get Started</a>
        @endauth
    </nav>
</header>
