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
        <p class="mt-3 text-xs text-stone-500">Like this supplier? Add them to your favorites so your planner knows. Your planner coordinates everything with the supplier for you.</p>
    </div>
</x-layouts.app>
