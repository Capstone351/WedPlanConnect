@php $editing = $item->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit Item' : 'Add Item'" :heading="$editing ? 'Edit '.$item->name : 'Add Inventory Item'">
    <form method="POST" action="{{ $editing ? route('inventory.update', $item) : route('inventory.store') }}" class="card max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="label">Item name</label>
                <input id="name" name="name" value="{{ old('name', $item->name) }}" required maxlength="150" class="input" placeholder="e.g. White chiavari chairs">
            </div>
            <div>
                <label for="category" class="label">Category</label>
                <select id="category" name="category" required class="input">
                    @foreach ($categories as $c)<option @selected(old('category', $item->category) === $c)>{{ $c }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="unit" class="label">Unit of measurement</label>
                <input id="unit" name="unit" value="{{ old('unit', $item->unit) }}" required maxlength="20" class="input" placeholder="pcs, sets, meters, bundles">
            </div>
            <div>
                <label for="quantity" class="label">Quantity in stock</label>
                <input id="quantity" name="quantity" type="number" min="0" value="{{ old('quantity', $item->quantity) }}" required class="input">
                @if ($editing)<p class="hint">Direct edits are logged as a manual correction. Use Adjust stock for usage and restocking.</p>@endif
            </div>
            <div>
                <label for="min_threshold" class="label">Minimum threshold</label>
                <input id="min_threshold" name="min_threshold" type="number" min="0" value="{{ old('min_threshold', $item->min_threshold) }}" required class="input">
                <p class="hint">An alert appears when stock is at or below this level.</p>
            </div>
            <div>
                <label for="unit_cost" class="label">Unit cost (₱) <span class="font-normal text-stone-400">(optional)</span></label>
                <input id="unit_cost" name="unit_cost" type="number" min="0" step="0.01" value="{{ old('unit_cost', $item->unit_cost) }}" class="input">
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-4">
            <a href="{{ route('inventory.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">Save item</button>
        </div>
    </form>
</x-layouts.app>
