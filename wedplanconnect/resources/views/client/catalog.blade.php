<x-layouts.app title="Supplier Catalog" heading="Supplier Catalog" subheading="Browse our trusted suppliers and tap the heart on the ones you like. Your planner handles all supplier coordination for you.">
    @if (auth()->user()->role !== 'client')
        <div class="mb-6 rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-gold-700">
            <strong>Staff preview.</strong> This is the supplier catalog exactly as couples see it. Supplier contact details are hidden here on purpose.
        </div>
    @elseif ($preferred->isNotEmpty())
        <div class="mb-6 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800">
            <span>♥ You have <strong>{{ $preferred->count() }}</strong> favorite {{ str('supplier')->plural($preferred->count()) }}. Your planner sees them when planning your wedding.</span>
            @if (request('favorites'))
                <a href="{{ route('client.catalog') }}" class="font-medium underline">Show all suppliers</a>
            @else
                <a href="{{ route('client.catalog', ['favorites' => 1]) }}" class="font-medium underline">Show only my favorites</a>
            @endif
        </div>
    @endif

    <nav class="mb-6 flex flex-wrap items-center gap-2" aria-label="Filter by category">
        @foreach (collect(['' => 'All'])->merge($categories->mapWithKeys(fn ($c) => [$c => $c])) as $value => $label)
            @php $current = (string) request('category') === (string) $value; @endphp
            <a href="{{ route('client.catalog', array_filter(['category' => $value, 'favorites' => request('favorites'), 'guests' => $guests])) }}"
                @class([
                    'rounded-full px-3.5 py-1.5 text-xs font-medium transition',
                    'bg-brand-600 text-white shadow-sm' => $current,
                    'bg-white text-stone-600 ring-1 ring-stone-200 hover:text-brand-700 hover:ring-brand-300' => ! $current,
                ])>{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Packages sized for the wedding: show suppliers with a package for at least this many guests --}}
    <form method="GET" action="{{ route('client.catalog') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl bg-white px-4 py-3 ring-1 ring-line">
        @foreach (array_filter(['category' => request('category'), 'favorites' => request('favorites')]) as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <div>
            <label for="guests" class="label">Packages good for at least</label>
            <div class="flex items-center gap-2">
                <input id="guests" name="guests" type="number" min="1" max="5000" value="{{ $guests }}" placeholder="{{ $clientGuests ?? 100 }}" class="input w-28">
                <span class="text-sm text-stone-600">guests</span>
            </div>
        </div>
        <button class="btn-primary">Find packages</button>
        @if ($guests)
            <a href="{{ route('client.catalog', array_filter(['category' => request('category'), 'favorites' => request('favorites')])) }}" class="btn-secondary">Clear</a>
        @elseif ($clientGuests)
            <a href="{{ route('client.catalog', array_filter(['category' => request('category'), 'favorites' => request('favorites'), 'guests' => $clientGuests])) }}" class="text-sm font-medium text-brand-700 hover:underline">Use my {{ number_format($clientGuests) }} guests</a>
        @endif
    </form>

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
                @elseif ($canFavorite)
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
                @if ($s->packages_count)
                    <p class="mt-3"><span class="badge-gold">{{ $s->packages_count }} {{ str('package')->plural($s->packages_count) }} · up to {{ number_format($s->max_package_guests) }} guests</span></p>
                @endif

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
            <div class="card col-span-full p-10 text-center text-stone-500">
                {{ $guests ? 'No suppliers have a package for '.number_format($guests).' or more guests here yet. Try a smaller number or ask your planner.' : 'No suppliers are available in this category yet.' }}
            </div>
        @endforelse
    </div>
</x-layouts.app>
