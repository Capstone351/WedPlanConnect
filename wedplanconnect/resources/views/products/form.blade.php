@php $editing = $product->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit Product' : 'Add Product'" :heading="$editing ? 'Edit '.$product->name : 'Add Product or Supply'" :subheading="$supplier->name.' · '.$supplier->category">
    <form method="POST" enctype="multipart/form-data"
        action="{{ $editing ? route($ctx['prefix'].'.update', [...$ctx['params'], $product->id]) : route($ctx['prefix'].'.store', $ctx['params']) }}" class="card max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="label">Product / service name</label>
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
