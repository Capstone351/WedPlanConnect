@props(['product', 'class' => 'aspect-[4/3] w-full'])
@if ($product->imageUrl())
    <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" loading="lazy" {{ $attributes->merge(['class' => "$class rounded-xl object-cover bg-stone-100"]) }}>
@else
    <div {{ $attributes->merge(['class' => "$class grid place-items-center rounded-xl bg-gradient-to-br from-brand-50 to-gold-100 text-brand-300"]) }} aria-hidden="true">
        <x-icon name="cube" class="size-8" />
    </div>
@endif
