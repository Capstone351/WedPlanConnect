<x-layouts.app title="My Wedding" heading="My Wedding" :subheading="'Welcome, '.auth()->user()->name">
    @if (! $booking)
        @php
            $me = auth()->user();
            $ready = [
                ['calendar', 'Set your wedding date', $me->wedding_date ? $me->wedding_date->format('l, F j, Y') : null, route('client.wedding-date'), 'So your planner knows when you want to marry.'],
                ['user', 'Choose your Wedding Planner', $me->preferredPlanner?->name, route('client.planner'), 'Pick the planner you would like to work with.'],
                ['cash', 'Set your budget', $me->budget ? '₱'.number_format((float) $me->budget->total_amount, 0) : null, route('client.budget'), 'Your planner will plan within it.'],
                ['heart', 'Save favorite suppliers', ($n = $me->favoriteSuppliers()->count()) ? $n.' '.str('favorite')->plural($n) : null, route('client.catalog'), 'Tap the heart on suppliers you like.'],
            ];
            $doneCount = collect($ready)->filter(fn ($s) => $s[2])->count();
        @endphp
        <div class="hero-panel mb-6">
            <div class="relative px-6 py-8 text-center sm:px-10">
                @if ($me->coupleNames())
                    <div class="eyebrow text-gold-300">The wedding of</div>
                    <h2 class="mt-2 font-display text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $me->coupleNames() }}</h2>
                    <x-ornament class="mx-auto mt-4 w-56" :light="true" />
                    <p class="mt-4 font-display text-xl text-gold-100">Let's start planning your wedding</p>
                @else
                    <div class="eyebrow text-gold-300">Get ready for your planner</div>
                    <h2 class="mt-2 font-display text-3xl font-semibold text-white">Let's start planning your wedding</h2>
                    <a href="{{ route('account.edit') }}" class="mt-2 inline-block text-sm text-gold-200 underline hover:text-white">Add the bride's and groom's names</a>
                @endif
                <p class="mx-auto mt-2 max-w-xl text-sm text-brand-100/85">Fill in these details. Your Wedding Planner sees them and uses them to create your booking. Your preparation progress will then appear here.</p>
                <div class="mx-auto mt-5 h-2 max-w-sm overflow-hidden rounded-full bg-white/15">
                    <div class="h-2 rounded-full bg-gradient-to-r from-gold-300 to-gold-500" style="width: {{ $doneCount / count($ready) * 100 }}%"></div>
                </div>
                <div class="mt-2 text-xs text-gold-200">{{ $doneCount }} of {{ count($ready) }} done</div>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($ready as [$icon, $label, $value, $url, $help])
                <a href="{{ $url }}" class="card card-hover flex items-start gap-4 p-5">
                    <span @class([
                        'grid size-11 shrink-0 place-items-center rounded-full ring-1',
                        'bg-emerald-50 text-emerald-600 ring-emerald-200' => $value,
                        'bg-gold-50 text-gold-600 ring-gold-300' => ! $value,
                    ])>
                        @if ($value)<x-icon name="check" class="size-5" />@else<x-icon :name="$icon" class="size-5" />@endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-display text-lg font-semibold">{{ $label }}</span>
                        @if ($value)
                            <span class="block text-sm font-medium text-emerald-700">{{ $value }}</span>
                        @else
                            <span class="block text-sm text-stone-500">{{ $help }}</span>
                        @endif
                    </span>
                    <span class="self-center text-sm font-medium text-brand-700">{{ $value ? 'Change' : 'Start' }} →</span>
                </a>
            @endforeach
        </div>
    @else
        @php
            $done = $booking->tasks->where('status', 'completed')->count();
            $confirmed = $booking->bookingSuppliers->where('status', 'confirmed')->count();
        @endphp
        <div class="hero-panel">
            <div class="relative flex flex-col items-center gap-6 px-6 pt-10 pb-8 text-center sm:px-10">
                <div class="eyebrow text-gold-300">The wedding of</div>
                <h2 class="font-display text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ auth()->user()->coupleNames() ?? $booking->client_name }}</h2>
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
                @php $myBudget = auth()->user()->budget; @endphp
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Your budget</h2>
                        <a href="{{ route('client.budget') }}" class="text-sm link">{{ $myBudget ? 'Update' : 'Set budget' }}</a>
                    </div>
                    <div class="card-body text-sm">
                        @if ($myBudget)
                            <div class="flex items-end justify-between gap-3">
                                <div class="font-display text-3xl font-semibold tabular-nums">₱{{ number_format((float) $myBudget->total_amount, 0) }}</div>
                                @if ($myBudget->guest_count)<div class="text-xs text-stone-500">{{ number_format($myBudget->guest_count) }} guests</div>@endif
                            </div>
                            <p class="mt-1 text-xs text-stone-500">Shared with your planner, {{ $booking->planner->name }}.</p>
                        @else
                            <p class="text-stone-600">Let your planner know how much you'd like to spend, so they can plan suppliers and décor within it.</p>
                            <a href="{{ route('client.budget') }}" class="btn-primary btn-sm mt-3">Set my budget</a>
                        @endif
                    </div>
                </div>
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
                    <div class="card-header"><h2 class="card-title">Your favorite suppliers</h2><a href="{{ route('client.catalog') }}" class="text-sm link">Browse catalog</a></div>
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
