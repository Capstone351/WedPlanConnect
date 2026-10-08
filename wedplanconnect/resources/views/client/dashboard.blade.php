<x-layouts.app title="My Wedding" heading="My Wedding" :subheading="'Welcome, '.auth()->user()->name">
    @if (! $booking)
        <div class="card mx-auto max-w-2xl p-10 text-center">
            <div class="mx-auto grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="heart" class="size-7" /></div>
            <h2 class="mt-4 font-display text-2xl font-semibold">Your wedding booking isn't linked yet</h2>
            <p class="mt-2 text-stone-600">Once your Wedding Planner creates your booking under this account, your preparation progress will appear here. In the meantime, browse our suppliers or ask WedBot a question.</p>
            <a href="{{ route('client.catalog') }}" class="btn-primary mt-6">Browse supplier catalog</a>
        </div>
    @else
        @php
            $done = $booking->tasks->where('status', 'completed')->count();
            $confirmed = $booking->bookingSuppliers->where('status', 'confirmed')->count();
        @endphp
        <div class="hero-panel">
            <div class="relative flex flex-col items-center gap-6 px-6 pt-10 pb-8 text-center sm:px-10">
                <div class="eyebrow text-gold-300">The wedding of</div>
                <h2 class="font-display text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $booking->client_name }}</h2>
                <x-ornament class="w-56" :light="true" />
                <div>
                    <p class="font-display text-lg text-gold-100">{{ $booking->event_date->format('l, F j, Y') }}</p>
                    <p class="mt-1 text-sm text-brand-100/80">{{ $booking->venue }} · {{ $booking->package }} · Planner: {{ $booking->planner->name }}</p>
                </div>
                <x-progress-ring :value="$progress" :size="132" :dark="true" />
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <span class="badge-gold">{{ ucfirst($booking->status) }}</span>
                    <a href="{{ route('client.status', $booking) }}" class="btn-gold"><x-icon name="qr" class="size-4" /> View full status page</a>
                </div>
            </div>
            <div class="relative grid grid-cols-2 border-t border-gold-400/25 bg-black/15 sm:grid-cols-4">
                @foreach ([
                    [max(0, $booking->daysToGo()), 'days remaining'],
                    [$confirmed.'/'.$booking->bookingSuppliers->count(), 'suppliers booked'],
                    [$done.'/'.$booking->tasks->count(), 'tasks completed'],
                    [round($paid / max(1, $booking->total_amount) * 100).'%', '₱'.number_format($paid).' of ₱'.number_format($booking->total_amount).' paid'],
                ] as [$value, $label])
                    <div class="p-5 text-center">
                        <div class="font-display text-3xl font-semibold text-gold-gradient tabular-nums">{{ $value }}</div>
                        <div class="mt-1 text-xs text-brand-100/80">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Wedding preparation</h2></div>
                <ul class="divide-y divide-stone-100 text-sm">
                    @forelse ($booking->tasks as $t)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="flex items-center gap-2">
                                <span @class(['grid size-5 place-items-center rounded-full text-[10px] text-white', 'bg-emerald-500' => $t->status === 'completed', 'bg-sky-500' => $t->status === 'ongoing', 'bg-stone-300' => $t->status === 'pending'])>{{ $t->status === 'completed' ? '✓' : '' }}</span>
                                {{ $t->title }}
                            </span>
                            <span class="text-xs text-stone-500">{{ $t->due_date->format('M j') }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-stone-500">Your planner hasn't added preparation milestones yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="space-y-6">
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Your suppliers</h2></div>
                    <ul class="divide-y divide-stone-100 text-sm">
                        @forelse ($booking->bookingSuppliers as $a)
                            <li class="flex items-center justify-between px-5 py-3">
                                <span>{{ $a->supplier->name }} <span class="text-xs text-stone-500">· {{ $a->supplier->category }}</span></span>
                                <span class="badge-{{ \App\Models\BookingSupplier::badge($a->status) }}">{{ ucfirst($a->status) }}</span>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-stone-500">No suppliers assigned yet.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Your wedding set-up</h2></div>
                    <ul class="divide-y divide-stone-100 text-sm">
                        @forelse ($booking->bookingProducts as $item)
                            <li class="flex items-center gap-3 px-5 py-2.5">
                                <x-product-image :product="$item->product" class="size-10 shrink-0" />
                                <span class="flex-1">{{ $item->product->name }} <span class="text-xs text-stone-500">· {{ $item->product->supplier->name }}</span></span>
                                <span class="text-stone-500">×{{ $item->quantity }}</span>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-stone-500">Your planner hasn't chosen set-up items yet.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="card">
                    <div class="card-header"><h2 class="card-title">Your preferred suppliers</h2><a href="{{ route('client.catalog') }}" class="text-sm link">Browse catalog</a></div>
                    <ul class="divide-y divide-stone-100 text-sm">
                        @forelse ($booking->preferences as $p)
                            <li class="px-5 py-2.5">{{ $p->supplier->name }} <span class="text-xs text-stone-500">· {{ $p->supplier->category }}</span></li>
                        @empty
                            <li class="px-5 py-6 text-center text-stone-500">Pick your favorites in the supplier catalog. Your planner will review them.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        @if ($otherBookings->isNotEmpty())
            <div class="card mt-6">
                <div class="card-header"><h2 class="card-title">Other bookings</h2></div>
                <ul class="divide-y divide-stone-100 text-sm">
                    @foreach ($otherBookings as $b)
                        <li class="flex items-center justify-between px-5 py-3">
                            <span>{{ $b->event_date->format('M j, Y') }} · {{ $b->venue }}</span>
                            <span class="badge-{{ $b->statusBadge() }}">{{ ucfirst($b->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</x-layouts.app>
