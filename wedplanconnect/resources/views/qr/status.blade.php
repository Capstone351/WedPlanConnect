@php
    $milestones = $booking->tasks;
    $suppliers = $booking->bookingSuppliers;
@endphp
<x-layouts.guest :title="'Wedding Status · '.$booking->client_name" :chatbot="! $viaQr">
    <div class="min-h-screen bg-canvas">
        <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
            <div class="flex items-center justify-between">
                <div class="text-xs tracking-[0.2em] text-gold-700 uppercase">{{ config('wedplan.business_name') }}</div>
                @if ($viaQr)
                    <span class="badge-green"><x-icon name="lock" class="size-3.5" /> Verified access</span>
                @else
                    <a href="{{ route('client.dashboard') }}" class="text-sm link">← My dashboard</a>
                @endif
            </div>

            <div class="hero-panel mt-4">
                <div class="relative px-6 pt-10 pb-8 text-center sm:px-10">
                    <div class="eyebrow text-gold-300">The wedding of</div>
                    <h1 class="mt-3 font-display text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $booking->client_name }}</h1>
                    <x-ornament class="mx-auto mt-5 w-56" :light="true" />
                    <p class="mt-4 font-display text-lg text-gold-100">{{ $booking->event_date->format('l, F j, Y') }}</p>
                    <p class="mt-1 text-sm text-brand-100/80"><x-icon name="map" class="inline size-4" /> {{ $booking->venue }} · {{ $booking->package }}</p>
                    <x-progress-ring :value="$progress" :size="140" :dark="true" class="mt-6" />
                </div>
                <div class="relative grid grid-cols-2 border-t border-gold-400/25 bg-black/15 sm:grid-cols-4">
                    @foreach ([
                        [max(0, $booking->daysToGo()), 'days to go'],
                        [$suppliers->where('status', 'confirmed')->count().'/'.$suppliers->count(), 'suppliers confirmed'],
                        [$milestones->where('status', 'completed')->count().'/'.$milestones->count(), 'milestones done'],
                        [round($paid / max(1, $booking->total_amount) * 100).'%', 'contract paid'],
                    ] as [$value, $label])
                        <div class="p-5 text-center">
                            <div class="font-display text-3xl font-semibold text-gold-gradient tabular-nums">{{ $value }}</div>
                            <div class="mt-1 text-xs text-brand-100/80">{{ $label }}</div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card mt-6">
                <div class="card-header"><h2 class="card-title">Preparation milestones</h2></div>
                <ol class="divide-y divide-stone-100">
                    @forelse ($milestones as $t)
                        <li class="flex items-center gap-3 px-5 py-3 text-sm">
                            <span @class(['grid size-6 shrink-0 place-items-center rounded-full text-xs text-white', 'bg-emerald-500' => $t->status === 'completed', 'bg-sky-500' => $t->status === 'ongoing', 'bg-stone-300' => $t->status === 'pending'])>{{ $t->status === 'completed' ? '✓' : ($t->status === 'ongoing' ? '…' : '') }}</span>
                            <span class="flex-1">{{ $t->title }}</span>
                            <span class="badge-{{ \App\Models\Task::statusBadge($t->status) }}">{{ $t->status === 'ongoing' ? 'In progress' : ucfirst($t->status) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-stone-500">Milestones will appear here as your planner schedules them.</li>
                    @endforelse
                </ol>
            </div>

            @if ($booking->bookingProducts->isNotEmpty())
                <div class="card mt-6">
                    <div class="card-header"><h2 class="card-title">Your wedding set-up</h2></div>
                    <ul class="divide-y divide-stone-100">
                        @foreach ($booking->bookingProducts as $item)
                            <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                                <span><span class="font-medium">{{ $item->product->name }}</span> <span class="text-stone-500">· {{ $item->product->supplier->name }}</span></span>
                                <span class="text-stone-500">×{{ $item->quantity }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card mt-6">
                <div class="card-header"><h2 class="card-title">Supplier confirmations</h2></div>
                <ul class="divide-y divide-stone-100">
                    @forelse ($suppliers as $a)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <span><span class="font-medium">{{ $a->supplier->category }}</span> <span class="text-stone-500">· {{ $a->supplier->name }}</span></span>
                            <span class="badge-{{ \App\Models\BookingSupplier::badge($a->status) }}">{{ ucfirst($a->status) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-stone-500">Supplier arrangements are still being prepared.</li>
                    @endforelse
                </ul>
            </div>

            <p class="mt-8 text-center text-xs text-stone-500">
                View-only page · Last updated {{ now()->format('M j, Y g:i A') }} · Questions? Contact your planner, {{ $booking->planner->name }}.
            </p>
        </div>
    </div>
</x-layouts.guest>
