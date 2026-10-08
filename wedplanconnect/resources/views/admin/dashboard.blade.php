<x-layouts.app title="Admin Dashboard" heading="Admin Dashboard" :subheading="'Overview of '.config('wedplan.business_name').' operations · '.now()->format('l, F j, Y')">
    <x-slot:actions>
        <a href="{{ route('admin.users.create') }}" class="btn-secondary"><x-icon name="plus" class="size-4" /> New user</a>
        <a href="{{ route('bookings.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> New booking</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Upcoming weddings" :value="$upcomingCounts->sum()" :hint="($upcomingCounts['confirmed'] ?? 0).' confirmed · '.($upcomingCounts['pending'] ?? 0).' pending'" icon="calendar" />
        <x-stat label="Payments collected" :value="'₱'.number_format($collected, 2)" :hint="'₱'.number_format($collectedThisMonth, 2).' this month · ₱'.number_format($outstanding, 2).' outstanding'" icon="cash" tone="green" />
        <x-stat label="Open tasks" :value="$openTasks" :hint="$overdueCount.' overdue'" icon="clipboard" :tone="$overdueCount ? 'red' : 'blue'" />
        <x-stat label="Low-stock items" :value="$lowStock->count()" hint="at or below threshold" icon="cube" :tone="$lowStock->isNotEmpty() ? 'amber' : 'green'" />
    </div>

    @if ($setup)
        <div class="card mt-6 border-brand-200">
            <div class="card-header"><h2 class="card-title">Finish setting up WedPlanConnect</h2></div>
            <ul class="divide-y divide-stone-100">
                @foreach ($setup as [$label, , $url])
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span>{{ $label }}</span>
                        <a href="{{ $url }}" class="btn-secondary btn-sm">Start</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($awaitingClosure->isNotEmpty())
        <div class="mt-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            <x-icon name="warning" class="mt-0.5 size-5 shrink-0" />
            <div>
                <strong>{{ $awaitingClosure->count() }} past wedding(s) still marked {{ $awaitingClosure->pluck('status')->unique()->join(' / ') }}.</strong>
                Mark them Completed or Cancelled so reports stay accurate:
                @foreach ($awaitingClosure as $b)
                    <a href="{{ route('bookings.show', $b) }}" class="font-medium underline">{{ $b->client_name }} ({{ $b->event_date->format('M j') }})</a>@if (! $loop->last), @endif
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3 lg:items-start">
        <div class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Upcoming weddings</h2>
                <a href="{{ route('bookings.index') }}" class="text-sm link">All bookings</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Date</th><th>Client</th><th>Venue</th><th>Planner</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($upcoming as $b)
                            <tr>
                                <td class="whitespace-nowrap">{{ $b->event_date->format('M j, Y') }}<div class="text-xs text-stone-500">{{ $b->daysToGo() }} days to go</div></td>
                                <td><a href="{{ route('bookings.show', $b) }}" class="link">{{ $b->client_name }}</a></td>
                                <td>{{ $b->venue }}</td>
                                <td>{{ $b->planner->name }}</td>
                                <td><span class="badge-{{ $b->statusBadge() }}">{{ ucfirst($b->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-stone-500">No upcoming weddings.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">User accounts</h2><a href="{{ route('admin.users.index') }}" class="text-sm link">Manage</a></div>
                <div class="card-body grid grid-cols-2 gap-3">
                    @foreach (\App\Models\User::ROLES as $key => $label)
                        <div class="rounded-xl bg-stone-50 p-3">
                            <div class="text-xl font-semibold tabular-nums">{{ $userCounts[$key] ?? 0 }}</div>
                            <div class="text-xs text-stone-500">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">Supplier statuses</h2></div>
                <div class="card-body space-y-2">
                    @foreach (\App\Models\BookingSupplier::STATUSES as $s)
                        <div class="flex items-center justify-between text-sm">
                            <span class="badge-{{ \App\Models\BookingSupplier::badge($s) }}">{{ ucfirst($s) }}</span>
                            <span class="font-semibold tabular-nums">{{ $supplierStatuses[$s] ?? 0 }}</span>
                        </div>
                    @endforeach
                    <p class="pt-1 text-xs text-stone-500">Across upcoming weddings</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Overdue tasks</h2><a href="{{ route('tasks.index', ['overdue' => 1]) }}" class="text-sm link">View all</a></div>
            <ul class="divide-y divide-stone-100">
                @forelse ($overdueTasks as $t)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div>
                            <div class="font-medium">{{ $t->title }}</div>
                            <div class="text-xs text-stone-500">{{ $t->booking?->client_name ?? 'General' }} · {{ $t->assignee?->name }}</div>
                        </div>
                        <span class="badge-red">Due {{ $t->due_date->format('M j') }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-stone-500">No overdue tasks. 🎉</li>
                @endforelse
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Inventory alerts</h2><a href="{{ route('inventory.index', ['low' => 1]) }}" class="text-sm link">View all</a></div>
            <ul class="divide-y divide-stone-100">
                @forelse ($lowStock->take(6) as $i)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div>
                            <div class="font-medium">{{ $i->name }}</div>
                            <div class="text-xs text-stone-500">{{ $i->category }} · min {{ $i->min_threshold }} {{ $i->unit }}</div>
                        </div>
                        <span class="badge-{{ $i->stockBadge() }}">{{ $i->quantity }} {{ $i->unit }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-stone-500">All stock levels are healthy.</li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
