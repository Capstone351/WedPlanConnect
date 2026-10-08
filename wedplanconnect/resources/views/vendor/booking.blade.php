<x-layouts.app title="Booking Details" :heading="$booking->client_name" :subheading="'Booking #'.$booking->id">
    <x-slot:actions>
        <a href="{{ route('vendor.bookings') }}" class="btn-secondary">Back</a>
    </x-slot:actions>

    <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Event</h2></div>
            <dl class="card-body space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-stone-500">Event date</dt><dd class="font-medium">{{ $booking->event_date->format('l, F j, Y') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-stone-500">Days to go</dt><dd>{{ max(0, $booking->daysToGo()) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-stone-500">Venue</dt><dd class="text-right">{{ $booking->venue }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-stone-500">Package</dt><dd>{{ $booking->package }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-stone-500">Booking status</dt><dd><span class="badge-{{ $booking->statusBadge() }}">{{ ucfirst($booking->status) }}</span></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-stone-500">Your coordination status</dt><dd><span class="badge-{{ \App\Models\BookingSupplier::badge($assignment->status) }}">{{ ucfirst($assignment->status) }}</span></dd></div>
            </dl>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Wedding Planner</h2></div>
            <dl class="card-body space-y-3 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-stone-500">Name</dt><dd class="font-medium">{{ $booking->planner->name }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-stone-500">Email</dt><dd>{{ $booking->planner->email }}</dd></div>
                @if ($booking->planner->phone)
                    <div class="flex justify-between gap-4"><dt class="text-stone-500">Phone</dt><dd>{{ $booking->planner->phone }}</dd></div>
                @endif
                <p class="pt-2 text-xs text-stone-500">All coordination for this wedding goes through the Wedding Planner. Client contact details are not shared with suppliers.</p>
            </dl>
        </div>

        <div class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Your products ordered for this wedding</h2>
                <span class="text-xs text-stone-500">{{ $items->sum('quantity') }} unit(s)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Product</th><th class="text-right">Qty</th><th>Planner notes</th><th class="text-right">Subtotal</th></tr></thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-product-image :product="$item->product" class="size-10 shrink-0" />
                                        <span class="font-medium">{{ $item->product->name }}</span>
                                    </div>
                                </td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-stone-600">{{ $item->notes ?: '—' }}</td>
                                <td class="text-right tabular-nums">{{ $item->subtotal() !== null ? '₱'.number_format($item->subtotal(), 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-stone-500">The planner hasn't selected specific products from you yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
