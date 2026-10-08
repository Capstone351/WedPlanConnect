<x-layouts.app title="Supplier Catalog" heading="Supplier Catalog" subheading="Browse our trusted suppliers and tap the heart on the ones you like. Your planner handles all supplier coordination for you.">
    @if (auth()->user()->role !== 'client')
        <div class="mb-6 rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-gold-700">
            <strong>Staff preview.</strong> This is the supplier catalog exactly as couples see it. Supplier contact details are hidden here on purpose.
        </div>
    @elseif (! $booking)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            You can browse freely. Saving favorites becomes available once your planner links your wedding booking to this account.
        </div>
    @endif

    <nav class="mb-6 flex flex-wrap items-center gap-2" aria-label="Filter by category">
        @foreach (collect(['' => 'All'])->merge($categories->mapWithKeys(fn ($c) => [$c => $c])) as $value => $label)
            @php $current = (string) request('category') === (string) $value; @endphp
            <a href="{{ route('client.catalog', array_filter(['category' => $value])) }}"
                @class([
                    'rounded-full px-3.5 py-1.5 text-xs font-medium transition',
                    'bg-brand-600 text-white shadow-sm' => $current,
                    'bg-white text-stone-600 ring-1 ring-stone-200 hover:text-brand-700 hover:ring-brand-300' => ! $current,
                ])>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($suppliers as $s)
            @php $prefId = $preferred[$s->id] ?? null; @endphp
            <div @class(['card card-hover relative flex flex-col p-5', 'ring-2 ring-brand-300' => $prefId])>
                <div class="flex items-start justify-between gap-2 pr-10">
                    <span class="badge-brand">{{ $s->category }}</span>
                    @if ($s->availability !== 'available')<span class="badge-gray">Unavailable</span>@endif
                </div>

                {{-- Favorite toggle --}}
                @if ($prefId)
                    <form method="POST" action="{{ route('client.preferences.destroy', $prefId) }}" class="absolute top-4 right-4 z-10">
                        @csrf @method('DELETE')
                        <button class="grid size-9 place-items-center rounded-full bg-brand-600 text-white shadow-sm hover:bg-brand-700" title="Remove from my favorites" aria-label="Remove {{ $s->name }} from my favorites">
                            <svg class="size-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>
                        </button>
                    </form>
                @elseif ($booking)
                    <form method="POST" action="{{ route('client.preferences.store') }}" class="absolute top-4 right-4 z-10">
                        @csrf
                        <input type="hidden" name="supplier_id" value="{{ $s->id }}">
                        <button class="grid size-9 place-items-center rounded-full bg-white text-brand-500 ring-1 ring-brand-200 hover:bg-brand-50" title="Add to my favorites" aria-label="Add {{ $s->name }} to my favorites">
                            <x-icon name="heart" class="size-5" />
                        </button>
                    </form>
                @endif

                <h3 class="mt-3 text-lg font-semibold"><a href="{{ route('client.catalog.show', $s) }}" class="after:absolute after:inset-0 hover:text-brand-700">{{ $s->name }}</a></h3>
                <p class="mt-1 flex-1 text-sm text-stone-600">{{ $s->description ?: 'Details available from your planner.' }}</p>

                <div class="mt-4 flex items-center justify-between border-t border-stone-100 pt-3 text-sm">
                    <span>
                        @if ($s->starting_price)
                            <span class="text-stone-500">From</span> <span class="font-semibold">₱{{ number_format($s->starting_price) }}</span>
                        @endif
                    </span>
                    <span class="font-medium text-brand-700">
                        {{ $s->offerings_count ? $s->offerings_count.' '.str('offering')->plural($s->offerings_count) : 'View profile' }} →
                    </span>
                </div>
            </div>
        @empty
            <div class="card col-span-full p-10 text-center text-stone-500">No suppliers are available in this category yet.</div>
        @endforelse
    </div>
</x-layouts.app>
