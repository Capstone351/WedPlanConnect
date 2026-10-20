<x-layouts.app :title="$supplier->name" :heading="$supplier->name" :subheading="$supplier->category">
    <x-slot:actions>
        <a href="{{ route('client.catalog') }}" class="btn-secondary">← Back to catalog</a>
        @if ($preferenceId)
            <form method="POST" action="{{ route('client.preferences.destroy', $preferenceId) }}">
                @csrf @method('DELETE')
                <button class="btn-secondary">♥ In your favorites · Remove</button>
            </form>
        @elseif ($canFavorite)
            <form method="POST" action="{{ route('client.preferences.store') }}">
                @csrf
                <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
                <button class="btn-primary">♡ Add to my favorites</button>
            </form>
        @endif
    </x-slot:actions>

    <div class="card mb-6 p-6">
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge-brand">{{ $supplier->category }}</span>
            @if ($supplier->availability !== 'available')<span class="badge-gray">Currently unavailable</span>@endif
            @if ($supplier->starting_price)<span class="text-sm text-stone-600">Starts at <strong>₱{{ number_format($supplier->starting_price) }}</strong></span>@endif
        </div>
        <p class="mt-3 text-stone-700">{{ $supplier->description ?: 'Details available from your planner.' }}</p>
        <p class="mt-3 text-xs text-stone-500">Like something here? Add this supplier to your preferences and tell your planner which items you'd like. Your planner coordinates everything with the supplier for you.</p>
    </div>

    @php [$packages, $singles] = $products->partition(fn ($p) => $p->is_package); @endphp

    @if ($packages->isNotEmpty())
        <h2 class="mb-3 font-display text-xl font-semibold">Packages</h2>
        <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($packages as $product)
                @php $fits = $clientGuests && $product->guest_capacity >= $clientGuests; @endphp
                <div @class(['card flex flex-col p-4', 'ring-2 ring-emerald-300' => $chosen->has($product->id)])>
                    <x-product-image :product="$product" />
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="badge-gold">Package · {{ $product->guestLabel() }}</span>
                        @if ($fits)
                            <span class="badge-green">Fits your {{ number_format($clientGuests) }} guests</span>
                        @elseif ($clientGuests)
                            <span class="badge-gray">Smaller than your {{ number_format($clientGuests) }} guests</span>
                        @endif
                    </div>
                    <div class="mt-2 flex items-start justify-between gap-2">
                        <h3 class="font-semibold">{{ $product->name }}</h3>
                        @if ($chosen->has($product->id))
                            <span class="badge-green">In your set-up</span>
                        @endif
                    </div>
                    <div class="mt-0.5 text-sm font-medium text-brand-700">{{ $product->priceLabel() }}</div>
                    @if (($left = $product->unitsLeft()) !== null && $left <= $product->low_stock_threshold)
                        <div class="mt-1"><span class="badge-{{ $left === 0 ? 'red' : 'amber' }}">{{ $left === 0 ? 'Out of stock' : 'Only '.$left.' left' }}</span></div>
                    @endif
                    @if ($product->description)
                        <p class="mt-2 text-sm text-stone-600">{{ $product->description }}</p>
                    @endif
                    @if ($product->inclusionList())
                        <ul class="mt-3 space-y-1 border-t border-line pt-3 text-sm text-stone-700">
                            @foreach ($product->inclusionList() as $line)
                                <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" /> {{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <h2 class="mb-3 font-display text-xl font-semibold">{{ $packages->isNotEmpty() ? 'Other offerings' : 'Offerings' }}</h2>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($singles as $product)
            <div @class(['card flex flex-col p-4', 'ring-2 ring-emerald-300' => $chosen->has($product->id)])>
                <x-product-image :product="$product" />
                <div class="mt-3 flex items-start justify-between gap-2">
                    <h3 class="font-semibold">{{ $product->name }}</h3>
                    @if ($chosen->has($product->id))
                        <span class="badge-green">In your set-up ×{{ $chosen[$product->id] }}</span>
                    @endif
                </div>
                <div class="mt-0.5 text-sm font-medium text-brand-700">{{ $product->priceLabel() }}</div>
                    @if (($left = $product->unitsLeft()) !== null && $left <= $product->low_stock_threshold)
                        <div class="mt-1"><span class="badge-{{ $left === 0 ? 'red' : 'amber' }}">{{ $left === 0 ? 'Out of stock' : 'Only '.$left.' left' }}</span></div>
                    @endif
                <p class="mt-2 text-sm text-stone-600">{{ $product->description }}</p>
            </div>
        @empty
            <div class="card col-span-full p-10 text-center text-stone-500">{{ $packages->isNotEmpty() ? 'Only packages are listed for this supplier.' : "This supplier hasn't listed products yet. Ask your planner about their offerings." }}</div>
        @endforelse
    </div>
</x-layouts.app>
