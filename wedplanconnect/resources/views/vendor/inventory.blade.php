<x-layouts.app title="My Inventory" heading="My Inventory"
    subheading="Keep your stock up to date so Wedding Planners only order what you can deliver.">
    <x-slot:actions>
        <a href="{{ route('vendor.products.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add product</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Products" :value="$counts['products']" icon="cube" />
        <x-stat label="Low stock" :value="$counts['low']" icon="bell" tone="amber" />
        <x-stat label="Out of stock" :value="$counts['out']" icon="clipboard" tone="red" />
        <x-stat label="Hidden" :value="$counts['hidden']" icon="store" />
    </div>

    <div class="card mt-6">
        <div class="card-header">
            <h2 class="card-title">Stock levels</h2>
            <span class="text-xs text-stone-500">Left = in stock − ordered for upcoming weddings</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>In stock</th>
                        <th class="text-right">Ordered</th>
                        <th>Left</th>
                        <th>Warn when left ≤</th>
                        <th>Available</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        @php [$stockLabel, $stockTone] = $product->stockStatus(); $formId = 'inventory-'.$product->id; @endphp
                        <tr @class(['opacity-60' => ! $product->is_available])>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-product-image :product="$product" class="size-10 shrink-0" />
                                    <div>
                                        <a href="{{ route('vendor.products.edit', $product->id) }}" class="font-medium hover:text-brand-700">{{ $product->name }}</a>
                                        <div class="text-xs text-stone-500">{{ $product->is_package ? 'Package · '.$product->guestLabel() : $product->priceLabel() }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <input form="{{ $formId }}" name="stock_quantity" type="number" min="0" value="{{ $product->stock_quantity }}" placeholder="Not counted"
                                    class="input w-28 py-1 text-sm" aria-label="In stock for {{ $product->name }}">
                            </td>
                            <td class="text-right tabular-nums">{{ $product->reservedQuantity() }}</td>
                            <td><span class="badge-{{ $stockTone }} whitespace-nowrap">{{ $stockLabel }}</span></td>
                            <td>
                                <input form="{{ $formId }}" name="low_stock_threshold" type="number" min="0" required value="{{ $product->low_stock_threshold }}"
                                    class="input w-20 py-1 text-sm" aria-label="Low stock warning level for {{ $product->name }}">
                            </td>
                            <td>
                                <label class="flex items-center gap-2 text-sm">
                                    <input form="{{ $formId }}" type="checkbox" name="is_available" value="1" @checked($product->is_available) class="rounded border-stone-300 text-brand-600">
                                    {{ $product->is_available ? 'Shown' : 'Hidden' }}
                                </label>
                            </td>
                            <td class="text-right">
                                <form id="{{ $formId }}" method="POST" action="{{ route('vendor.inventory.update', $product->id) }}">
                                    @csrf @method('PATCH')
                                    <button class="btn-secondary btn-sm">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-stone-500">No products yet. Add your products to start tracking stock.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="border-t border-line px-5 py-3 text-xs text-stone-500">
            Leave <strong>In stock</strong> blank for made-to-order items and services. After a wedding, update your stock count for anything that was used up.
            Untick <strong>Available</strong> to hide a product from planners and couples without deleting it.
        </p>
    </div>
</x-layouts.app>
