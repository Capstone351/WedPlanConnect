<x-layouts.app title="Products & Supplies" :heading="$ctx['vendor'] ? 'My Products & Supplies' : $supplier->name.' · Offerings'"
    subheading="Wedding Planners choose from these when setting up a wedding, and couples can browse them in the Supplier Catalog.">
    <x-slot:actions>
        @unless ($ctx['vendor'])
            <a href="{{ route('suppliers.index') }}" class="btn-secondary">Back to suppliers</a>
        @endunless
        <a href="{{ route($ctx['prefix'].'.create', $ctx['params']) }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add product</a>
    </x-slot:actions>

    @if ($products->isEmpty())
        <div class="card mx-auto max-w-xl p-10 text-center">
            <div class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="cube" class="size-7" /></div>
            <h2 class="mt-4 font-display text-2xl font-semibold">No products yet</h2>
            <p class="mt-2 text-stone-600">Add the supplies, packages, or services you offer so planners can include them in a wedding set-up.</p>
            <a href="{{ route($ctx['prefix'].'.create', $ctx['params']) }}" class="btn-primary mt-6"><x-icon name="plus" class="size-4" /> Add your first product</a>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($products as $product)
                <div @class(['card flex flex-col p-4', 'opacity-60' => ! $product->is_available])>
                    <x-product-image :product="$product" />
                    <div class="mt-3 flex items-start justify-between gap-2">
                        <h3 class="font-semibold">{{ $product->name }}</h3>
                        <span class="badge-{{ $product->is_available ? 'green' : 'gray' }}">{{ $product->is_available ? 'Available' : 'Hidden' }}</span>
                    </div>
                    <div class="mt-0.5 text-sm font-medium text-brand-700">{{ $product->priceLabel() }}</div>
                    <p class="mt-2 line-clamp-3 flex-1 text-sm text-stone-600">{{ $product->description ?: 'No description.' }}</p>
                    <div class="mt-3 text-xs text-stone-500">Used in {{ $product->booking_products_count }} wedding set-up(s)</div>
                    <div class="mt-3 flex gap-2 border-t border-stone-100 pt-3">
                        <a href="{{ route($ctx['prefix'].'.edit', [...$ctx['params'], $product->id]) }}" class="btn-secondary btn-sm">Edit</a>
                        <form method="POST" action="{{ route($ctx['prefix'].'.destroy', [...$ctx['params'], $product->id]) }}" data-confirm="Remove “{{ $product->name }}” from your offerings? Weddings that already include it keep it.">
                            @csrf @method('DELETE')
                            <button class="btn-danger btn-sm">Remove</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
