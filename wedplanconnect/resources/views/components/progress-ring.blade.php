@props(['value' => 0, 'size' => 120, 'dark' => false])
@php
    $r = 52;
    $c = 2 * pi() * $r;
    $offset = $c * (1 - max(0, min(100, $value)) / 100);
    $id = 'ring'.uniqid();
@endphp
<div {{ $attributes->merge(['class' => 'relative inline-grid place-items-center']) }} style="width: {{ $size }}px; height: {{ $size }}px">
    <svg viewBox="0 0 120 120" class="absolute inset-0 -rotate-90">
        <defs>
            <linearGradient id="{{ $id }}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#ecdaae" />
                <stop offset="100%" stop-color="#b98d3f" />
            </linearGradient>
        </defs>
        <circle cx="60" cy="60" r="{{ $r }}" fill="none" stroke-width="9" class="{{ $dark ? 'stroke-white/15' : 'stroke-gold-100' }}" />
        <circle cx="60" cy="60" r="{{ $r }}" fill="none" stroke-width="9" stroke-linecap="round" stroke="url(#{{ $id }})"
            stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $offset }}" />
    </svg>
    <div class="text-center">
        <div class="font-display text-2xl font-semibold tabular-nums {{ $dark ? 'text-white' : 'text-ink' }}">{{ $value }}%</div>
        <div class="text-[10px] tracking-[0.18em] uppercase {{ $dark ? 'text-gold-300' : 'text-gold-700' }}">ready</div>
    </div>
</div>
