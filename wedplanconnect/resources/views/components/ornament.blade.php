{{-- Gold divider with a central diamond flourish. --}}
@props(['class' => 'w-40', 'light' => false])
<div {{ $attributes->merge(['class' => "$class flex items-center gap-2"]) }} aria-hidden="true">
    <span class="h-px flex-1 bg-gradient-to-r from-transparent {{ $light ? 'to-gold-300/70' : 'to-gold-400' }}"></span>
    <svg viewBox="0 0 24 12" class="h-3 w-6 {{ $light ? 'text-gold-300' : 'text-gold-500' }}" fill="currentColor">
        <path d="M12 0l4 6-4 6-4-6z"/><circle cx="3" cy="6" r="1.4"/><circle cx="21" cy="6" r="1.4"/>
    </svg>
    <span class="h-px flex-1 bg-gradient-to-l from-transparent {{ $light ? 'to-gold-300/70' : 'to-gold-400' }}"></span>
</div>
