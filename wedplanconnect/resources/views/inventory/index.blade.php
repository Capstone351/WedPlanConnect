<x-layouts.app title="Inventory" heading="Inventory Management" subheading="Decoration materials, rentables, and event supplies.">
    <x-slot:actions>
        <a href="{{ route('inventory.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add Item</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Total items" :value="$stats['items']" icon="cube" />
        <x-stat label="Units in stock" :value="number_format($stats['units'])" icon="check" tone="green" />
        <x-stat label="Low-stock items" :value="$stats['low']" icon="warning" :tone="$stats['low'] ? 'amber' : 'green'" />
        <x-stat label="Inventory value" :value="'₱'.number_format($stats['value'], 2)" icon="cash" tone="gold" />
    </div>

    @if ($lowItems->isNotEmpty())
        <div class="mt-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            <x-icon name="warning" class="mt-0.5 size-5 shrink-0" />
            <div>
                <strong>{{ $lowItems->count() }} item(s) at or below minimum stock:</strong>
                {{ $lowItems->map(fn ($i) => "{$i->name} ({$i->quantity} {$i->unit})")->join(', ') }}.
                <a href="{{ route('inventory.index', ['low' => 1]) }}" class="font-medium underline">Show only these</a>
            </div>
        </div>
    @endif

    <div class="card mt-6">
        <form class="flex flex-wrap items-end gap-3 border-b border-stone-100 p-4">
            <div class="min-w-48 flex-1">
                <label class="label" for="q">Search</label>
                <input id="q" name="q" value="{{ request('q') }}" placeholder="Item name" class="input">
            </div>
            <div>
                <label class="label" for="category">Category</label>
                <select id="category" name="category" class="input" data-autosubmit>
                    <option value="">All categories</option>
                    @foreach ($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach
                </select>
            </div>
            <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" name="low" value="1" data-autosubmit @checked(request('low'))> Low stock only</label>
            <button class="btn-secondary">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Item</th><th>Category</th><th class="text-right">Quantity</th><th class="text-right">Min.</th><th>Status</th><th>Adjust stock</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="font-medium">{{ $item->name }}@if ($item->unit_cost)<div class="text-xs font-normal text-stone-500">₱{{ number_format($item->unit_cost, 2) }} / {{ $item->unit }}</div>@endif</td>
                            <td>{{ $item->category }}</td>
                            <td class="text-right tabular-nums">{{ $item->quantity }} {{ $item->unit }}</td>
                            <td class="text-right tabular-nums text-stone-500">{{ $item->min_threshold }}</td>
                            <td><span class="badge-{{ $item->stockBadge() }}">{{ $item->stockLabel() }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('inventory.adjust', $item) }}" class="flex flex-wrap items-center gap-1.5">
                                    @csrf
                                    <select name="direction" class="input w-auto py-1 text-xs" aria-label="Direction">
                                        <option value="out">Use −</option>
                                        <option value="in">Restock +</option>
                                    </select>
                                    <input name="amount" type="number" min="1" required class="input w-20 py-1 text-xs" aria-label="Amount">
                                    <select name="booking_id" class="input w-36 py-1 text-xs" aria-label="For booking">
                                        <option value="">No booking</option>
                                        @foreach ($bookings as $b)<option value="{{ $b->id }}">{{ $b->client_name }} ({{ $b->event_date->format('M j') }})</option>@endforeach
                                    </select>
                                    <button class="btn-secondary btn-sm">Apply</button>
                                </form>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('inventory.edit', $item) }}" class="btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('inventory.destroy', $item) }}" data-confirm="Remove {{ $item->name }} from inventory?">@csrf @method('DELETE')<button class="btn-icon" title="Remove {{ $item->name }}" aria-label="Remove {{ $item->name }}"><x-icon name="trash" class="size-4" /></button></form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-stone-500">No inventory items found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $items->links() }}</div>
    </div>
</x-layouts.app>
