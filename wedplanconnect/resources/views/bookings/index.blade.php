<x-layouts.app title="Bookings" heading="Booking Management" subheading="All wedding bookings with real-time double-booking validation.">
    <x-slot:actions>
        <a href="{{ route('bookings.create') }}" class="btn-primary"><x-icon name="plus" class="size-4" /> Add New Booking</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Total bookings" :value="$stats['total']" icon="calendar" />
        <x-stat label="Confirmed" :value="$stats['confirmed']" icon="check" tone="green" />
        <x-stat label="Pending" :value="$stats['pending']" icon="bell" tone="amber" />
        <x-stat label="Total revenue" :value="'₱'.number_format($stats['revenue'], 2)" hint="payments received" icon="cash" tone="gold" />
    </div>

    <div class="card mt-6">
        <form class="flex flex-wrap items-end gap-3 border-b border-stone-100 p-4">
            <div class="min-w-48 flex-1">
                <label class="label" for="q">Search</label>
                <input id="q" name="q" value="{{ request('q') }}" placeholder="Couple, venue, or package" class="input">
            </div>
            <div>
                <label class="label" for="status">Status</label>
                <select id="status" name="status" class="input" data-autosubmit>
                    <option value="">All</option>
                    @foreach (\App\Models\Booking::STATUSES as $s)
                        <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label" for="from">Event from</label><input id="from" type="date" name="from" value="{{ request('from') }}" class="input"></div>
            <div><label class="label" for="to">to</label><input id="to" type="date" name="to" value="{{ request('to') }}" class="input"></div>
            <button class="btn-secondary">Filter</button>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Couple</th><th>Venue</th><th>Event date</th><th>Status</th><th>Payment</th><th class="text-right">Contract</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($bookings as $b)
                        @php
                            $paid = (float) $b->payments_sum_amount;
                            $pay = $paid <= 0 ? ['Unpaid', 'red'] : ($paid >= (float) $b->total_amount ? ['Paid', 'green'] : ['Partial', 'amber']);
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('bookings.show', $b) }}" class="font-medium link">{{ $b->client_name }}</a>
                                <div class="text-xs text-stone-500">#{{ $b->id }} · {{ $b->package }}@if (auth()->user()->role === 'admin') · {{ $b->planner->name }}@endif</div>
                            </td>
                            <td>{{ $b->venue }}</td>
                            <td class="whitespace-nowrap">{{ $b->event_date->format('M j, Y') }}</td>
                            <td><span class="badge-{{ $b->statusBadge() }}">{{ ucfirst($b->status) }}</span></td>
                            <td><span class="badge-{{ $pay[1] }}">{{ $pay[0] }}</span></td>
                            <td class="text-right tabular-nums">₱{{ number_format($b->total_amount, 2) }}</td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('bookings.show', $b) }}" class="btn-secondary btn-sm">View</a>
                                    @if (in_array($b->status, ['pending', 'confirmed']))
                                        <a href="{{ route('bookings.edit', $b) }}" class="btn-secondary btn-sm">Edit</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-10 text-center text-stone-500">No bookings found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $bookings->links() }}</div>
    </div>
</x-layouts.app>
