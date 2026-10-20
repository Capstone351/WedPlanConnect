<x-layouts.app title="Booking Details" :heading="$booking->client_name" :subheading="'Booking #'.$booking->id">
    <x-slot:actions>
        <a href="{{ route('vendor.bookings') }}" class="btn-secondary">Back</a>
    </x-slot:actions>

    {{-- The planner's order: the vendor accepts or declines it --}}
    @if ($assignment->awaitingReply())
        <div class="mb-6 rounded-2xl border border-gold-300 bg-gold-50 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="font-display text-lg font-semibold text-ink">New order to review</h2>
                    <p class="mt-1 text-sm text-stone-700">{{ $booking->planner->name }} sent this order {{ $assignment->order_sent_at->diffForHumans() }}. Check the products and ready-by dates below, then accept or decline.</p>
                </div>
                <form method="POST" action="{{ route('vendor.bookings.respond', $booking) }}">
                    @csrf
                    <input type="hidden" name="decision" value="accept">
                    <button class="btn-primary"><x-icon name="check" class="size-4" /> Accept order</button>
                </form>
            </div>
            <details class="group mt-3" @if ($errors->has('note')) open @endif>
                <summary class="cursor-pointer list-none text-sm font-medium text-red-700 hover:underline">Can't take this order? Decline it</summary>
                <form method="POST" action="{{ route('vendor.bookings.respond', $booking) }}" class="mt-3 space-y-2">
                    @csrf
                    <input type="hidden" name="decision" value="decline">
                    <label class="label" for="note">Reason for the planner</label>
                    <textarea id="note" name="note" rows="2" maxlength="255" required class="input" placeholder="e.g. Fully booked on this date">{{ old('note') }}</textarea>
                    <button class="btn-secondary text-red-700">Decline order</button>
                </form>
            </details>
        </div>
    @elseif ($assignment->responded_at)
        <div @class(['mb-6 rounded-xl px-4 py-3 text-sm ring-1', 'bg-emerald-50 text-emerald-800 ring-emerald-200' => $assignment->status === 'confirmed', 'bg-red-50 text-red-700 ring-red-200' => $assignment->status !== 'confirmed'])>
            You {{ $assignment->status === 'confirmed' ? 'accepted' : 'declined' }} this order on {{ $assignment->responded_at->format('M j, Y g:i A') }}{{ $assignment->response_note ? ': "'.$assignment->response_note.'"' : '.' }}
        </div>
    @elseif (! $assignment->order_sent_at)
        <div class="mb-6 rounded-xl bg-stone-50 px-4 py-3 text-sm text-stone-600 ring-1 ring-line">
            The Wedding Planner is still preparing your order. You'll be notified when it's ready to accept.
        </div>
    @endif

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
            @if ($items->isNotEmpty())
                <p class="border-b border-line px-5 py-2.5 text-xs text-stone-500">Have each product ready by its date so it can be set up for the wedding. Ask the Wedding Planner if a date doesn't work for you.</p>
            @endif
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Product</th><th>Ready by</th><th class="text-right">Qty</th><th>Planner notes</th><th class="text-right">Subtotal</th></tr></thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-product-image :product="$item->product" class="size-10 shrink-0" />
                                        <span class="font-medium">{{ $item->product->name }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    @php [$dueLabel, $dueTone] = $item->dueStatus(); @endphp
                                    <div class="font-medium">{{ $item->due_date?->format('D, M j, Y') ?? '—' }}</div>
                                    <span class="badge-{{ $dueTone }} mt-1">{{ $dueLabel }}</span>
                                </td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-stone-600">{{ $item->notes ?: '—' }}</td>
                                <td class="text-right tabular-nums">{{ $item->subtotal() !== null ? '₱'.number_format($item->subtotal(), 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-stone-500">The planner hasn't selected specific products from you yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
