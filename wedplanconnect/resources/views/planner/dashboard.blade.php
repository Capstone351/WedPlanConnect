<x-layouts.app title="Planner Dashboard" :heading="'Good day, '.strtok(auth()->user()->name, ' ').'!'" :subheading="now()->format('l, F j, Y')">
    <x-slot:actions>
        <a href="{{ route('tasks.create') }}" class="btn-secondary"><x-icon name="plus" class="size-4" /> Task</a>
        <a href="{{ route('bookings.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> New booking</a>
    </x-slot:actions>

    @if ($featured)
        <div class="hero-panel mb-6">
            <div class="relative flex flex-col gap-6 p-7 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                <div>
                    <div class="eyebrow text-gold-300">Next wedding</div>
                    <div class="mt-2 font-display text-4xl font-semibold tracking-tight">{{ $featured->client_name }}</div>
                    <div class="mt-2 text-brand-100/90">{{ $featured->event_date->format('l, F j, Y') }} · {{ $featured->venue }} · {{ $featured->package }}</div>
                    <a href="{{ route('bookings.show', $featured) }}" class="btn-gold mt-5">Open booking →</a>
                </div>
                <div class="flex items-center gap-7">
                    <div class="text-center">
                        <div class="font-display text-5xl font-semibold text-gold-gradient tabular-nums">{{ $featured->daysToGo() }}</div>
                        <div class="mt-1 text-[11px] tracking-[0.2em] text-gold-300/90 uppercase">days to go</div>
                    </div>
                    <x-progress-ring :value="$featured->progress()" :size="112" :dark="true" />
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Upcoming weddings" :value="$counts['upcoming']" icon="calendar" />
        <x-stat label="Confirmed suppliers" :value="$counts['suppliers']" hint="across active bookings" icon="store" tone="green" />
        <x-stat label="Pending tasks" :value="$counts['pendingTasks']" :hint="$counts['overdue'].' overdue'" icon="clipboard" :tone="$counts['overdue'] ? 'red' : 'blue'" />
        <x-stat label="Low-stock alerts" :value="$lowStock->count()" icon="cube" :tone="$lowStock->isNotEmpty() ? 'amber' : 'green'" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3 lg:items-start">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Upcoming bookings</h2><a href="{{ route('bookings.index') }}" class="text-sm link">View all</a></div>
            <ul class="divide-y divide-stone-100">
                @forelse ($upcoming as $b)
                    <li>
                        <a href="{{ route('bookings.show', $b) }}" class="flex items-center gap-4 px-5 py-3 hover:bg-brand-50/40">
                            <div class="w-14 shrink-0 rounded-xl bg-brand-50 py-1.5 text-center">
                                <div class="text-[10px] font-semibold text-brand-600 uppercase">{{ $b->event_date->format('M') }}</div>
                                <div class="text-lg leading-none font-semibold">{{ $b->event_date->format('j') }}</div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ $b->client_name }}</div>
                                <div class="truncate text-xs text-stone-500">{{ $b->venue }} · {{ $b->package }}</div>
                            </div>
                            <div class="hidden w-32 sm:block">
                                <div class="h-1.5 rounded-full bg-stone-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ $b->progress() }}%"></div></div>
                                <div class="mt-1 text-right text-[11px] text-stone-500">{{ $b->progress() }}% ready</div>
                            </div>
                            <span class="badge-{{ $b->statusBadge() }}">{{ ucfirst($b->status) }}</span>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-10 text-center text-sm text-stone-500">No upcoming weddings. <a href="{{ route('bookings.create') }}" class="link">Create a booking</a></li>
                @endforelse
            </ul>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Pending tasks</h2><a href="{{ route('tasks.index') }}" class="text-sm link">All tasks</a></div>
                <ul class="divide-y divide-stone-100">
                    @forelse ($tasks as $t)
                        <li class="flex items-start gap-3 px-5 py-3 text-sm">
                            <form method="POST" action="{{ route('tasks.status', $t) }}" class="pt-0.5">
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="completed">
                                <button class="grid size-5 place-items-center rounded-md border border-stone-300 hover:border-brand-500 hover:bg-brand-50" title="Mark completed" aria-label="Mark {{ $t->title }} completed"></button>
                            </form>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium">{{ $t->title }}</div>
                                <div class="text-xs text-stone-500">{{ $t->booking?->client_name ?? 'General' }} · due {{ $t->due_date->format('M j') }}</div>
                            </div>
                            @if ($t->is_overdue)<span class="badge-red">Overdue</span>@endif
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-stone-500">All caught up!</li>
                    @endforelse
                </ul>
            </div>

            @if ($newPreferences->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Client supplier picks</h2></div>
                    <ul class="divide-y divide-stone-100 text-sm">
                        @foreach ($newPreferences as $p)
                            <li class="px-5 py-2.5">
                                <a href="{{ route('bookings.show', $p->booking_id) }}" class="link">{{ $p->booking->client_name }}</a>
                                <span class="text-stone-500">prefers</span> {{ $p->supplier->name }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($lowStock->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Low stock</h2><a href="{{ route('inventory.index', ['low' => 1]) }}" class="text-sm link">Inventory</a></div>
                    <ul class="divide-y divide-stone-100 text-sm">
                        @foreach ($lowStock as $i)
                            <li class="flex items-center justify-between px-5 py-2.5"><span>{{ $i->name }}</span><span class="badge-{{ $i->stockBadge() }}">{{ $i->quantity }} {{ $i->unit }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
