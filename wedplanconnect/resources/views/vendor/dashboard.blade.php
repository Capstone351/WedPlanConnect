<x-layouts.app title="Vendor Dashboard" :heading="$supplier?->name ?? 'Vendor Dashboard'" :subheading="$supplier ? $supplier->category.' · Supplier workspace' : null">
    @if (! $supplier)
        <div class="card mx-auto max-w-xl p-10 text-center">
            <h2 class="font-display text-2xl font-semibold">No supplier profile linked</h2>
            <p class="mt-2 text-stone-600">Ask the {{ config('wedplan.business_name') }} administrator to link your account to your supplier catalog profile.</p>
        </div>
    @else
        <x-slot:actions>
            <form method="POST" action="{{ route('vendor.availability') }}" class="flex items-center gap-2">
                @csrf @method('PATCH')
                <label for="availability" class="text-sm text-stone-600">Service availability</label>
                <select id="availability" name="availability" class="input w-auto" data-autosubmit>
                    <option value="available" @selected($supplier->availability === 'available')>Available</option>
                    <option value="unavailable" @selected($supplier->availability === 'unavailable')>Unavailable</option>
                </select>
            </form>
        </x-slot:actions>

        @unless ($supplier->products()->exists())
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900">
                <span><strong>List your products and supplies</strong> so Wedding Planners can include them in weddings and couples can browse them.</span>
                <a href="{{ route('vendor.products.create') }}" class="btn-primary btn-sm"><x-icon name="plus" class="size-4" /> Add product</a>
            </div>
        @endunless

        @if ($next)
            <div class="hero-panel mb-6">
                <div class="relative flex flex-col gap-4 p-7 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <div>
                        <div class="eyebrow text-gold-300">Next engagement</div>
                        <div class="mt-2 font-display text-3xl font-semibold tracking-tight">{{ $next->booking->client_name }}</div>
                        <div class="mt-1 text-brand-100/90">{{ $next->booking->event_date->format('l, F j, Y') }} · {{ $next->booking->venue }}</div>
                        <a href="{{ route('vendor.bookings.show', $next->booking_id) }}" class="btn-gold mt-5">View details →</a>
                    </div>
                    <div class="text-center">
                        <div class="font-display text-5xl font-semibold text-gold-gradient tabular-nums">{{ $next->booking->daysToGo() }}</div>
                        <div class="mt-1 text-[11px] tracking-[0.2em] text-gold-300/90 uppercase">days to go</div>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="Active bookings" :value="$counts['active']" icon="calendar" />
            <x-stat label="Upcoming events" :value="$counts['upcoming']" icon="bell" tone="blue" />
            <x-stat label="This week" :value="$counts['thisWeek']" icon="clipboard" tone="amber" />
            <x-stat label="Completed" :value="$counts['completed']" icon="check" tone="green" />
        </div>

        <div class="card mt-6">
            <div class="card-header"><h2 class="card-title">Current assignments</h2><a href="{{ route('vendor.bookings') }}" class="text-sm link">All bookings</a></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Event date</th><th>Couple</th><th>Venue</th><th>Package</th><th>Coordination</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($assignments as $a)
                            <tr>
                                <td class="whitespace-nowrap">{{ $a->booking->event_date->format('M j, Y') }}</td>
                                <td class="font-medium">{{ $a->booking->client_name }}</td>
                                <td>{{ $a->booking->venue }}</td>
                                <td>{{ $a->booking->package }}</td>
                                <td><span class="badge-{{ \App\Models\BookingSupplier::badge($a->status) }}">{{ ucfirst($a->status) }}</span></td>
                                <td class="text-right"><a href="{{ route('vendor.bookings.show', $a->booking_id) }}" class="btn-secondary btn-sm">Details</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-10 text-center text-stone-500">No upcoming assignments.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
