<x-layouts.app title="My Profile" heading="My Catalog Profile" subheading="This is how your business appears in the supplier catalog. Contact the administrator to update details.">
    @if (! $supplier)
        <div class="card p-10 text-center text-stone-500">No supplier profile is linked to your account yet.</div>
    @else
        <div class="card max-w-2xl p-6">
            <div class="flex items-start justify-between gap-3">
                <span class="badge-brand">{{ $supplier->category }}</span>
                <span class="badge-{{ $supplier->availability === 'available' ? 'green' : 'gray' }}">{{ ucfirst($supplier->availability) }}</span>
            </div>
            <h2 class="mt-3 font-display text-2xl font-semibold">{{ $supplier->name }}</h2>
            <p class="mt-2 text-stone-600">{{ $supplier->description ?: 'No description yet.' }}</p>
            <dl class="mt-6 grid gap-3 border-t border-stone-100 pt-4 text-sm sm:grid-cols-2">
                <div><dt class="text-stone-500">Phone</dt><dd class="font-medium">{{ $supplier->phone }}</dd></div>
                <div><dt class="text-stone-500">Email</dt><dd class="font-medium">{{ $supplier->email ?: '—' }}</dd></div>
                <div><dt class="text-stone-500">Starting price</dt><dd class="font-medium">{{ $supplier->starting_price ? '₱'.number_format($supplier->starting_price, 2) : '—' }}</dd></div>
            </dl>
            <form method="POST" action="{{ route('vendor.availability') }}" class="mt-6 flex items-center gap-3 border-t border-stone-100 pt-4">
                @csrf @method('PATCH')
                <label for="availability" class="label mb-0">Update availability</label>
                <select id="availability" name="availability" class="input w-auto">
                    <option value="available" @selected($supplier->availability === 'available')>Available</option>
                    <option value="unavailable" @selected($supplier->availability === 'unavailable')>Unavailable</option>
                </select>
                <button class="btn-primary">Save</button>
            </form>
        </div>
    @endif
</x-layouts.app>
