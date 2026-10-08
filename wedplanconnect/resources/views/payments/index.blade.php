<x-layouts.app title="Payments" heading="Payment Management" subheading="Record client payments manually and monitor outstanding contract balances.">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat label="Contract value" :value="'₱'.number_format($totals['contract'], 2)" icon="clipboard" />
        <x-stat label="Collected" :value="'₱'.number_format($totals['collected'], 2)" icon="cash" tone="green" />
        <x-stat label="Outstanding" :value="'₱'.number_format($totals['contract'] - $totals['collected'], 2)" icon="bell" tone="amber" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <div class="card lg:col-span-3">
            <div class="card-header">
                <h2 class="card-title">Payment ledger</h2>
                <form class="flex gap-2">
                    @if (request('booking_id'))<input type="hidden" name="booking_id" value="{{ request('booking_id') }}">@endif
                    <select name="payment_status" class="input py-1 text-xs" data-autosubmit aria-label="Payment status">
                        <option value="">All</option>
                        @foreach (['paid' => 'Paid', 'partial' => 'Partial', 'unpaid' => 'Unpaid'] as $k => $v)
                            <option value="{{ $k }}" @selected(request('payment_status') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Client</th><th>Package</th><th class="text-right">Total</th><th class="text-right">Paid</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($bookings as $b)
                            @php
                                $paid = (float) $b->payments_sum_amount;
                                $st = $paid <= 0 ? ['Unpaid', 'red'] : ($paid >= (float) $b->total_amount ? ['Paid', 'green'] : ['Partial', 'amber']);
                            @endphp
                            <tr @class(['bg-brand-50/60' => $selected?->id === $b->id])>
                                <td><a href="{{ route('payments.index', ['booking_id' => $b->id]) }}" class="font-medium link">{{ $b->client_name }}</a><div class="text-xs text-stone-500">{{ $b->event_date->format('M j, Y') }}</div></td>
                                <td>{{ $b->package }}</td>
                                <td class="text-right tabular-nums">₱{{ number_format($b->total_amount, 2) }}</td>
                                <td class="text-right tabular-nums">₱{{ number_format($paid, 2) }}</td>
                                <td><span class="badge-{{ $st[1] }}">{{ $st[0] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-stone-500">No bookings to show.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <form method="POST" action="{{ route('payments.store') }}" class="card">
                @csrf
                <div class="card-header"><h2 class="card-title">Record new payment</h2></div>
                <div class="card-body space-y-4">
                    <div>
                        <label for="booking_id" class="label">Booking</label>
                        <select id="booking_id" name="booking_id" required class="input">
                            <option value="">Select booking…</option>
                            @foreach ($allBookings as $b)
                                <option value="{{ $b->id }}" @selected(old('booking_id', $selected?->id) == $b->id)>{{ $b->client_name }} — {{ $b->event_date->format('M j, Y') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="amount" class="label">Amount (₱)</label>
                            <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required class="input">
                        </div>
                        <div>
                            <label for="method" class="label">Method</label>
                            <select id="method" name="method" required class="input">
                                @foreach (\App\Models\Payment::METHODS as $k => $v)<option value="{{ $k }}" @selected(old('method') === $k)>{{ $v }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="payment_date" class="label">Date</label>
                            <input id="payment_date" name="payment_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('payment_date', today()->toDateString()) }}" required class="input">
                        </div>
                        <div>
                            <label for="receipt_number" class="label">Receipt #</label>
                            <input id="receipt_number" name="receipt_number" value="{{ old('receipt_number') }}" maxlength="50" class="input">
                        </div>
                    </div>
                    <div>
                        <label for="notes" class="label">Notes</label>
                        <textarea id="notes" name="notes" rows="2" maxlength="2000" class="input" placeholder="e.g. Reservation fee">{{ old('notes') }}</textarea>
                    </div>
                    <button class="btn-primary w-full">Save payment</button>
                    <p class="text-center text-xs text-stone-500">Manual recording only. Online payment processing is out of scope.</p>
                </div>
            </form>

            @if ($selected)
                @php $sPaid = $selected->payments->sum('amount'); @endphp
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">{{ $selected->client_name }}</h2>
                        <a href="{{ route('bookings.contract', $selected) }}" class="btn-secondary btn-sm"><x-icon name="download" class="size-4" /> PDF</a>
                    </div>
                    <div class="grid grid-cols-3 gap-2 border-b border-stone-100 p-4 text-center text-sm">
                        <div><div class="text-xs text-stone-500">Contract</div><div class="font-semibold tabular-nums">₱{{ number_format($selected->total_amount, 2) }}</div></div>
                        <div><div class="text-xs text-stone-500">Paid</div><div class="font-semibold text-emerald-700 tabular-nums">₱{{ number_format($sPaid, 2) }}</div></div>
                        <div><div class="text-xs text-stone-500">Balance</div><div class="font-semibold text-amber-700 tabular-nums">₱{{ number_format($selected->total_amount - $sPaid, 2) }}</div></div>
                    </div>
                    <ul class="divide-y divide-stone-100 text-sm">
                        @forelse ($selected->payments as $p)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div>
                                    <div class="font-medium tabular-nums">₱{{ number_format($p->amount, 2) }} <span class="text-xs font-normal text-stone-500">· {{ $p->methodLabel() }}</span></div>
                                    <div class="text-xs text-stone-500">{{ $p->payment_date->format('M j, Y') }}{{ $p->receipt_number ? ' · OR '.$p->receipt_number : '' }}{{ $p->notes ? ' · '.$p->notes : '' }}</div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('payments.edit', $p) }}" class="btn-secondary btn-sm">Edit</a>
                                    <form method="POST" action="{{ route('payments.destroy', $p) }}" data-confirm="Void this payment record?">@csrf @method('DELETE')<button class="btn-icon" title="Void payment" aria-label="Void payment"><x-icon name="trash" class="size-4" /></button></form>
                                </div>
                            </li>
                        @empty
                            <li class="px-5 py-6 text-center text-stone-500">No payments recorded yet.</li>
                        @endforelse
                    </ul>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
