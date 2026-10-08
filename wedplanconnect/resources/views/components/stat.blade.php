@props(['label', 'value', 'hint' => null, 'icon' => null, 'tone' => 'brand'])
@php
    $tones = [
        'brand' => 'from-brand-50 to-brand-100 text-brand-600',
        'green' => 'from-emerald-50 to-emerald-100 text-emerald-600',
        'amber' => 'from-amber-50 to-amber-100 text-amber-600',
        'red' => 'from-red-50 to-red-100 text-red-600',
        'blue' => 'from-sky-50 to-sky-100 text-sky-600',
        'gold' => 'from-gold-50 to-gold-100 text-gold-600',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'card relative flex items-start gap-4 overflow-hidden p-5']) }}>
    <span class="pointer-events-none absolute inset-x-5 top-0 h-px bg-gradient-to-r from-transparent via-gold-400/70 to-transparent"></span>
    @if ($icon)
        <div class="grid size-11 shrink-0 place-items-center rounded-full bg-gradient-to-br ring-1 ring-gold-300/60 {{ $tones[$tone] ?? $tones['brand'] }}">
            <x-icon :name="$icon" class="size-5" />
        </div>
    @endif
    <div class="min-w-0">
        <div class="text-[11px] font-semibold tracking-[0.14em] text-gold-700 uppercase">{{ $label }}</div>
        <div class="mt-1 truncate font-display text-[1.7rem] leading-tight font-semibold text-ink tabular-nums">{{ $value }}</div>
        @if ($hint)
            <div class="mt-0.5 text-xs text-stone-500">{{ $hint }}</div>
        @endif
    </div>
</div>
