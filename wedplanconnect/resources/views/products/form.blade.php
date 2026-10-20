@php $editing = $product->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit Product' : 'Add Product'" :heading="$editing ? 'Edit '.$product->name : 'Add Product or Supply'" :subheading="$supplier->name.' · '.$supplier->category">
    <form method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route($ctx['prefix'].'.update', [...$ctx['params'], $product->id]) : route($ctx['prefix'].'.store', $ctx['params']) }}" class="group card max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        @php $isPackage = (bool) old('is_package', $product->is_package); @endphp
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <fieldset class="sm:col-span-2">
                <legend class="label">What are you listing?</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="check-chip">
                        <input type="radio" name="is_package" value="0" @checked(! $isPackage) class="text-brand-600">
                        <span><span class="block font-medium">Single product or service</span><span class="block text-xs text-stone-500">e.g. a bridal bouquet, a cake</span></span>
                    </label>
                    <label class="check-chip">
                        <input id="kind_package" type="radio" name="is_package" value="1" @checked($isPackage) class="text-brand-600">
                        <span><span class="block font-medium">Package</span><span class="block text-xs text-stone-500">A bundle for a number of guests</span></span>
                    </label>
                </div>
            </fieldset>
            <div class="hidden gap-5 group-has-[#kind_package:checked]:grid sm:col-span-2 sm:grid-cols-2">
                <div>
                    <label for="guest_capacity" class="label">Good for how many guests?</label>
                    <input id="guest_capacity" name="guest_capacity" type="number" min="1" max="5000" value="{{ old('guest_capacity', $product->guest_capacity) }}" class="input" placeholder="e.g. 100">
                    <p class="hint">Couples looking for this many guests or fewer will see it as a fit.</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="inclusions" class="label">What's included <span class="font-normal text-stone-400">(one per line)</span></label>
                    <textarea id="inclusions" name="inclusions" rows="5" maxlength="2000" class="input" placeholder="Bridal bouquet&#10;6 entourage bouquets&#10;Ceremony arch florals&#10;15 reception centerpieces">{{ old('inclusions', $product->inclusions) }}</textarea>
                </div>
            </div>
            <div class="sm:col-span-2">
                <label for="name" class="label">Product, service or package name</label>
                <input id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="150" class="input" placeholder="e.g. Bridal bouquet (roses & peonies)">
            </div>
            <div class="sm:col-span-2">
                <label for="description" class="label">Description</label>
                <textarea id="description" name="description" rows="4" maxlength="2000" class="input" placeholder="What's included, sizes, colors, materials…">{{ old('description', $product->description) }}</textarea>
            </div>
            <div>
                <label for="price" class="label">Price (₱) <span class="font-normal text-stone-400">(optional)</span></label>
                <input id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', $product->price) }}" class="input">
                <p class="hint">Leave blank to show "Price on request".</p>
            </div>
            <div>
                <label for="unit" class="label">Priced</label>
                <input id="unit" name="unit" list="units" value="{{ old('unit', $product->unit) }}" maxlength="30" class="input" placeholder="per set">
                <datalist id="units">@foreach (\App\Models\SupplierProduct::UNITS as $u)<option value="{{ $u }}">@endforeach</datalist>
            </div>
            <div>
                <label for="stock_quantity" class="label">How many in stock? <span class="font-normal text-stone-400">(optional)</span></label>
                <input id="stock_quantity" name="stock_quantity" type="number" min="0" value="{{ old('stock_quantity', $product->stock_quantity) }}" class="input" placeholder="e.g. 20">
                <p class="hint">Leave blank for made-to-order items and services.</p>
            </div>
            <div>
                <label for="low_stock_threshold" class="label">Warn me when this many are left</label>
                <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 5) }}" class="input">
            </div>
            <div class="sm:col-span-2">
                <label for="image" class="label">Photo <span class="font-normal text-stone-400">(optional, JPG/PNG/WebP, up to 2 MB)</span></label>
                <div class="flex items-center gap-4">
                    @if ($editing && $product->image_path)
                        <x-product-image :product="$product" class="size-20" />
                    @endif
                    <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="block text-sm text-stone-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100">
                </div>
                @if ($editing && $product->image_path)
                    <label class="mt-2 flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" name="remove_image" value="1"> Remove current photo</label>
                @endif
            </div>
            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                <input type="checkbox" name="is_available" value="1" @checked(old('is_available', $product->is_available)) class="rounded border-stone-300 text-brand-600">
                Available. Untick to hide it from planners and couples without deleting it.
            </label>
        </div>
        <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-4">
            <a href="{{ route($ctx['prefix'].'.index', $ctx['params']) }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">{{ $editing ? 'Save changes' : 'Add product' }}</button>
        </div>
    </form>
</x-layouts.app>
