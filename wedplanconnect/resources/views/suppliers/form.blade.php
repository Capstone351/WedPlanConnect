@php $editing = $supplier->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit Supplier' : 'New Supplier'" :heading="$editing ? 'Edit '.$supplier->name : 'Add Supplier'">
    <form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card max-w-3xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="label">Business name</label>
                <input id="name" name="name" value="{{ old('name', $supplier->name) }}" required maxlength="150" class="input">
            </div>
            <div>
                <label for="category" class="label">Category</label>
                <select id="category" name="category" required class="input">
                    @foreach ($categories as $c)<option @selected(old('category', $supplier->category) === $c)>{{ $c }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="availability" class="label">Availability</label>
                <select id="availability" name="availability" class="input">
                    <option value="available" @selected(old('availability', $supplier->availability) === 'available')>Available</option>
                    <option value="unavailable" @selected(old('availability', $supplier->availability) === 'unavailable')>Unavailable</option>
                </select>
            </div>
            <div>
                <label for="phone" class="label">Phone</label>
                <input id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" required maxlength="20" placeholder="+639171234567" class="input">
            </div>
            <div>
                <label for="email" class="label">Email <span class="font-normal text-stone-400">(optional)</span></label>
                <input id="email" name="email" type="email" value="{{ old('email', $supplier->email) }}" maxlength="150" class="input">
            </div>
            <div>
                <label for="starting_price" class="label">Starting price (₱) <span class="font-normal text-stone-400">(optional)</span></label>
                <input id="starting_price" name="starting_price" type="number" min="0" step="0.01" value="{{ old('starting_price', $supplier->starting_price) }}" class="input">
            </div>
            <div class="sm:col-span-2">
                <label for="description" class="label">Services & description</label>
                <textarea id="description" name="description" rows="4" maxlength="2000" class="input" placeholder="Shown to couple-clients in the catalog.">{{ old('description', $supplier->description) }}</textarea>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-4">
            <a href="{{ route('suppliers.index') }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">Save supplier</button>
        </div>
    </form>
</x-layouts.app>
