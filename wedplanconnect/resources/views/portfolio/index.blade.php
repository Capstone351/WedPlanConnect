<x-layouts.app title="Portfolio" heading="Wedding Portfolio" subheading="Past weddings shown on the public website's front page and Our Weddings gallery.">
    <x-slot:actions>
        <a href="{{ route('weddings.index') }}" target="_blank" class="btn-secondary">View public gallery ↗</a>
        <a href="{{ route('portfolio.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add wedding</a>
    </x-slot:actions>

    @if ($completedWithoutEntry->isNotEmpty())
        <div class="mb-6 rounded-xl border border-gold-300 bg-gold-50 px-4 py-3 text-sm text-gold-700">
            <strong>Completed weddings not yet in the portfolio:</strong>
            @foreach ($completedWithoutEntry as $b)
                <a href="{{ route('portfolio.create', ['booking_id' => $b->id]) }}" class="ml-1 font-semibold underline">{{ $b->client_name }} ({{ $b->event_date->format('M Y') }})</a>@if (! $loop->last),@endif
            @endforeach
            <span class="block text-xs">Click one to start an entry with its details filled in.</span>
        </div>
    @endif

    @if ($entries->isEmpty())
        <div class="card mx-auto max-w-xl p-10 text-center">
            <div class="mx-auto grid size-14 place-items-center rounded-full bg-gold-50 text-gold-600 ring-1 ring-gold-300"><x-icon name="heart" class="size-7" /></div>
            <h2 class="mt-4 font-display text-2xl font-semibold">Showcase your past weddings</h2>
            <p class="mt-2 text-stone-600">Add photos and a short story of weddings FMT has styled. Published weddings appear first on the website's front page.</p>
            <a href="{{ route('portfolio.create') }}" class="btn-primary mt-6"><x-icon name="plus" class="size-4" /> Add your first wedding</a>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($entries as $entry)
                <div class="card flex flex-col overflow-hidden">
                    <a href="{{ route('portfolio.edit', $entry) }}" class="relative block">
                        <x-wedding-cover :entry="$entry" class="aspect-[16/10]" />
                        <span class="absolute top-3 left-3 flex gap-1.5">
                            @if ($entry->is_published)
                                <span class="badge-green">Published</span>
                            @else
                                <span class="badge-gray">Draft</span>
                            @endif
                            @if ($entry->is_featured)<span class="badge-gold">★ Featured</span>@endif
                        </span>
                    </a>
                    <div class="flex flex-1 flex-col p-4">
                        <h3 class="font-display text-xl font-semibold">{{ $entry->title }}</h3>
                        <p class="text-sm text-stone-500">{{ $entry->event_date?->format('F j, Y') }}@if ($entry->event_date && $entry->venue) · @endif{{ $entry->venue }}</p>
                        <p class="mt-2 text-xs text-stone-500">{{ $entry->photos_count }} {{ str('photo')->plural($entry->photos_count) }}{{ $entry->consent_confirmed ? '' : ' · consent not yet confirmed' }}</p>
                        <div class="mt-auto flex items-center gap-2 pt-4">
                            <a href="{{ route('portfolio.edit', $entry) }}" class="btn-secondary btn-sm">Edit</a>
                            @if ($entry->is_published)
                                <a href="{{ route('weddings.show', $entry) }}" target="_blank" class="btn-outline btn-sm">View on site ↗</a>
                            @endif
                            <form method="POST" action="{{ route('portfolio.destroy', $entry) }}" class="ml-auto" data-confirm="Remove “{{ $entry->title }}” from the portfolio?">
                                @csrf @method('DELETE')
                                <button class="btn-icon" title="Remove" aria-label="Remove {{ $entry->title }}"><x-icon name="trash" class="size-4" /></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
