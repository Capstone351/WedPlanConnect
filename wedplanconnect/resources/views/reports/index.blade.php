@php
    $maxTrend = max(1, $trend->max('value'));
    $statusTotal = max(1, $byStatus->sum());
    $statusColors = ['pending' => 'bg-amber-400', 'confirmed' => 'bg-emerald-500', 'completed' => 'bg-sky-500', 'cancelled' => 'bg-stone-300'];
    $isAdmin = auth()->user()->role === 'admin';
@endphp
<x-layouts.app title="Reports" heading="Reports & Analytics" :subheading="$isAdmin ? 'Strategic and operational reports for the whole business.' : 'Operational reports for your assigned bookings.'">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Total bookings" :value="$metrics['bookings']" icon="calendar" />
        <x-stat label="Revenue collected" :value="'₱'.number_format($metrics['revenue'], 2)" icon="cash" tone="green" />
        <x-stat label="This month" :value="'₱'.number_format($metrics['thisMonth'], 2)" icon="chart" tone="gold" />
        <x-stat label="Clients with upcoming weddings" :value="$metrics['activeClients']" icon="heart" tone="blue" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Revenue trend</h2><span class="text-xs text-stone-500">Payments received, last 6 months</span></div>
            <div class="card-body">
                <div class="flex h-48 items-end gap-3">
                    @foreach ($trend as $m)
                        <div class="flex flex-1 flex-col items-center gap-2">
                            <div class="text-[11px] text-stone-500 tabular-nums">{{ $m['value'] ? '₱'.number_format($m['value'] / 1000, 1).'k' : '' }}</div>
                            <div class="w-full rounded-t-lg bg-brand-400" style="height: {{ max(2, $m['value'] / $maxTrend * 140) }}px" title="₱{{ number_format($m['value'], 2) }}"></div>
                            <div class="text-xs font-medium text-stone-600">{{ $m['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Bookings by status</h2></div>
            <div class="card-body">
                <div class="flex h-3 overflow-hidden rounded-full bg-stone-100">
                    @foreach (\App\Models\Booking::STATUSES as $s)
                        @if ($byStatus[$s] ?? 0)
                            <div class="{{ $statusColors[$s] }}" style="width: {{ ($byStatus[$s] ?? 0) / $statusTotal * 100 }}%"></div>
                        @endif
                    @endforeach
                </div>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach (\App\Models\Booking::STATUSES as $s)
                        <li class="flex items-center justify-between">
                            <span class="flex items-center gap-2"><span class="size-2.5 rounded-full {{ $statusColors[$s] }}"></span>{{ ucfirst($s) }}</span>
                            <span class="font-semibold tabular-nums">{{ $byStatus[$s] ?? 0 }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    @foreach (['strategic' => 'Strategic reports', 'operational' => 'Operational reports'] as $kind => $label)
        @php $group = collect($types)->filter(fn ($t) => $t[1] === $kind); @endphp
        @if ($group->isNotEmpty())
            <h2 class="mt-8 mb-3 font-display text-xl font-semibold">{{ $label }}</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($group as $key => [$title, , $description])
                    <div class="card flex flex-col p-5">
                        <div class="font-semibold">{{ $title }}</div>
                        <p class="mt-1 flex-1 text-sm text-stone-600">{{ $description }}</p>
                        <div class="mt-4 flex gap-2">
                            <a href="{{ route('reports.show', $key) }}" class="btn-primary btn-sm">Generate</a>
                            <a href="{{ route('reports.pdf', $key) }}" class="btn-secondary btn-sm"><x-icon name="download" class="size-4" /> PDF</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach
</x-layouts.app>
