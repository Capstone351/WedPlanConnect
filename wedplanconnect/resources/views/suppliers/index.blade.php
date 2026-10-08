<x-layouts.app title="Suppliers" heading="Supplier Catalog" subheading="Centralized list of wedding service providers. Contact details are hidden from clients.">
    <x-slot:actions>
        <a href="{{ route('suppliers.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> New Supplier</a>
    </x-slot:actions>

    <form class="card mb-6 flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-48 flex-1">
            <label class="label" for="q">Search</label>
            <input id="q" name="q" value="{{ request('q') }}" placeholder="Supplier name" class="input">
        </div>
        <div>
            <label class="label" for="category">Category</label>
            <select id="category" name="category" class="input" data-autosubmit>
                <option value="">All categories</option>
                @foreach ($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="label" for="availability">Availability</label>
            <select id="availability" name="availability" class="input" data-autosubmit>
                <option value="">Any</option>
                <option value="available" @selected(request('availability') === 'available')>Available</option>
                <option value="unavailable" @selected(request('availability') === 'unavailable')>Unavailable</option>
            </select>
        </div>
        <div>
            <label class="label" for="status">Listing</label>
            <select id="status" name="status" class="input" data-autosubmit>
                <option value="">Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Deactivated</option>
            </select>
        </div>
        <button class="btn-secondary">Filter</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($suppliers as $s)
            <div class="card flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <span class="badge-brand">{{ $s->category }}</span>
                    @if ($s->trashed())
                        <span class="badge-gray">Deactivated</span>
                    @else
                        <span class="badge-{{ $s->availability === 'available' ? 'green' : 'gray' }}">{{ ucfirst($s->availability) }}</span>
                    @endif
                </div>
                <h3 class="mt-3 text-lg font-semibold">{{ $s->name }}</h3>
                <p class="mt-1 line-clamp-3 flex-1 text-sm text-stone-600">{{ $s->description ?: 'No description yet.' }}</p>
                <div class="mt-4 space-y-1 text-xs text-stone-500">
                    <div>📞 {{ $s->phone }} @if ($s->email) · ✉ {{ $s->email }} @endif</div>
                    <div>
                        @if ($s->starting_price) From ₱{{ number_format($s->starting_price) }} · @endif
                        {{ $s->active_assignments }} active booking(s) · {{ $s->products_count }} product(s)
                        @if ($s->user) · Vendor login: {{ $s->user->name }} @endif
                    </div>
                </div>
                <div class="mt-4 flex gap-2 border-t border-stone-100 pt-4">
                    @if ($s->trashed())
                        <form method="POST" action="{{ route('suppliers.restore', $s) }}">@csrf @method('PATCH')<button class="btn-success btn-sm">Reactivate</button></form>
                    @else
                        <a href="{{ route('suppliers.edit', $s) }}" class="btn-secondary btn-sm">Edit</a>
                        <a href="{{ route('suppliers.products.index', $s) }}" class="btn-secondary btn-sm">Products</a>
                        <form method="POST" action="{{ route('suppliers.destroy', $s) }}" data-confirm="Deactivate {{ $s->name }}? They will be removed from active listings.">@csrf @method('DELETE')<button class="btn-danger btn-sm">Deactivate</button></form>
                    @endif
                </div>
            </div>
        @empty
            <div class="card col-span-full p-10 text-center text-stone-500">No suppliers in this category.</div>
        @endforelse
    </div>
    <div class="mt-6">{{ $suppliers->links() }}</div>
</x-layouts.app>
