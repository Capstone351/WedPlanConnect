@php
    $paid = $booking->payments->sum('amount');
    $balance = (float) $booking->total_amount - $paid;
    $active = in_array($booking->status, ['pending', 'confirmed']);
@endphp
<x-layouts.app :title="'Booking #'.$booking->id" :heading="$booking->client_name"
    :subheading="'Booking #'.$booking->id.' · '.$booking->event_date->format('l, F j, Y').' · '.$booking->venue">
    <x-slot:actions>
        <span class="badge-{{ $booking->statusBadge() }} px-3 py-1 text-sm">{{ ucfirst($booking->status) }}</span>
        @if ($active)
            <a href="{{ route('bookings.edit', $booking) }}" class="btn-secondary">Edit</a>
        @endif
        @if ($booking->status === 'pending')
            <form method="POST" action="{{ route('bookings.confirm', $booking) }}" data-confirm="Confirm this booking and generate the client's QR code?">
                @csrf @method('PATCH')
                <button class="btn-success"><x-icon name="qr" class="size-4" /> Confirm & generate QR</button>
            </form>
        @elseif ($booking->status === 'confirmed')
            <form method="POST" action="{{ route('bookings.complete', $booking) }}" data-confirm="Mark this wedding as completed?">
                @csrf @method('PATCH')
                <button class="btn-secondary">Mark completed</button>
            </form>
        @elseif ($booking->status === 'completed')
            @if ($booking->portfolioEntry)
                <a href="{{ route('portfolio.edit', $booking->portfolioEntry) }}" class="btn-outline"><x-icon name="heart" class="size-4" /> Portfolio entry</a>
            @else
                <a href="{{ route('portfolio.create', ['booking_id' => $booking->id]) }}" class="btn-gold"><x-icon name="heart" class="size-4" /> Add to portfolio</a>
            @endif
        @endif
        @if ($active)
            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" data-confirm="Cancel this booking? Its QR code will stop working.">
                @csrf @method('PATCH')
                <button class="btn-danger">Cancel booking</button>
            </form>
        @endif
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Days to go" :value="$booking->daysToGo() >= 0 ? $booking->daysToGo() : 'Done'" :hint="$booking->event_date->format('M j, Y')" icon="calendar" />
        <x-stat label="Preparation" :value="$booking->progress().'%'" hint="tasks + confirmed suppliers" icon="check" tone="green" />
        <x-stat label="Paid" :value="'₱'.number_format($paid, 2)" :hint="'of ₱'.number_format($booking->total_amount, 2)" icon="cash" tone="gold" />
        <x-stat label="Balance" :value="'₱'.number_format($balance, 2)" :hint="$booking->paymentStatus()" icon="cash" :tone="$balance > 0 ? 'amber' : 'green'" />
    </div>

    {{-- Guided next steps from a new booking to the couple's QR code --}}
    @php
        $steps = [
            ['Assign suppliers', $booking->bookingSuppliers->isNotEmpty(), '#suppliers', 'Pick vendors (client favorites are pre-ticked).'],
            ['Choose set-up items', $booking->bookingProducts->isNotEmpty(), '#setup', "Select vendors' products for the wedding."],
            ['Add preparation tasks', $booking->tasks->isNotEmpty(), route('tasks.create', ['booking_id' => $booking->id]), 'Milestones the couple will see on their status page.'],
            ['Record the down payment', $booking->payments->isNotEmpty(), route('payments.index', ['booking_id' => $booking->id]), 'Log what the couple has paid so far.'],
            ['Confirm & print the QR code', $booking->status === 'confirmed', $booking->status === 'confirmed' ? route('bookings.qr', $booking) : null, 'Use "Confirm & generate QR" above, then print it for the contract.'],
        ];
        $doneCount = collect($steps)->where(1, true)->count();
    @endphp
    @if ($active && $doneCount < count($steps))
        <div class="card mt-6 print:hidden">
            <div class="card-header">
                <h2 class="card-title">Next steps</h2>
                <span class="text-xs text-stone-500">{{ $doneCount }} of {{ count($steps) }} done</span>
            </div>
            <ol class="grid gap-px bg-line sm:grid-cols-5">
                @foreach ($steps as $i => [$label, $done, $link, $help])
                    <li class="bg-white p-4">
                        <div class="flex items-center gap-2">
                            <span @class([
                                'grid size-6 shrink-0 place-items-center rounded-full text-xs font-semibold',
                                'bg-emerald-500 text-white' => $done,
                                'bg-brand-800 text-gold-200' => ! $done,
                            ])>{{ $done ? '✓' : $i + 1 }}</span>
                            @if ($link && ! $done)
                                <a href="{{ $link }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ $label }}</a>
                            @else
                                <span @class(['text-sm font-semibold', 'text-stone-400 line-through' => $done])>{{ $label }}</span>
                            @endif
                        </div>
                        <p class="mt-1.5 text-xs text-stone-500">{{ $help }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3 lg:items-start">
        <div class="space-y-6 lg:col-span-2">
            {{-- Supplier assignments (UC-04) --}}
            <div class="card scroll-mt-6" id="suppliers">
                <div class="card-header">
                    <h2 class="card-title">Suppliers</h2>
                    <span class="text-xs text-stone-500">{{ $booking->bookingSuppliers->where('status', 'confirmed')->count() }} / {{ $booking->bookingSuppliers->count() }} confirmed</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Supplier</th><th>Contact</th><th>Coordination status</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($booking->bookingSuppliers as $a)
                                <tr>
                                    <td><div class="font-medium">{{ $a->supplier->name }}</div><div class="text-xs text-stone-500">{{ $a->supplier->category }}</div></td>
                                    <td class="text-xs text-stone-600">{{ $a->supplier->phone }}<br>{{ $a->supplier->email }}</td>
                                    <td>
                                        @if ($active)
                                            <form method="POST" action="{{ route('bookings.suppliers.update', [$booking, $a]) }}">
                                                @csrf @method('PATCH')
                                                <select name="status" class="input py-1 text-xs" data-autosubmit aria-label="Coordination status">
                                                    @foreach (\App\Models\BookingSupplier::STATUSES as $s)
                                                        <option value="{{ $s }}" @selected($a->status === $s)>{{ ucfirst($s) }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @else
                                            <span class="badge-{{ \App\Models\BookingSupplier::badge($a->status) }}">{{ ucfirst($a->status) }}</span>
                                        @endif
                                        @if ($a->awaitingReply())
                                            <div class="mt-1 text-xs text-stone-500">Order sent {{ $a->order_sent_at->diffForHumans() }} · awaiting reply</div>
                                        @elseif ($a->responded_at)
                                            <div @class(['mt-1 text-xs', 'text-emerald-700' => $a->status === 'confirmed', 'text-red-700' => $a->status !== 'confirmed'])>
                                                {{ $a->status === 'confirmed' ? 'Accepted' : 'Declined' }} {{ $a->responded_at->diffForHumans() }}{{ $a->response_note ? ': "'.$a->response_note.'"' : '' }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($active)
                                            <div class="flex items-center justify-end gap-1">
                                                @if ($a->supplier->user || $a->supplier->email)
                                                    <form method="POST" action="{{ route('bookings.suppliers.send', [$booking, $a]) }}">
                                                        @csrf
                                                        <button class="btn-secondary btn-sm whitespace-nowrap" title="Notify {{ $a->supplier->name }} so they can accept or decline">{{ $a->order_sent_at ? 'Resend order' : 'Send order' }}</button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-stone-400" title="Add an email or vendor login for this supplier to send orders">No contact for orders</span>
                                                @endif
                                                <form method="POST" action="{{ route('bookings.suppliers.destroy', [$booking, $a]) }}" data-confirm="Remove {{ $a->supplier->name }} from this booking?">
                                                    @csrf @method('DELETE')
                                                    <button class="btn-icon" title="Remove {{ $a->supplier->name }}" aria-label="Remove {{ $a->supplier->name }}"><x-icon name="trash" class="size-4" /></button>
                                                </form>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-6 text-center text-stone-500">No suppliers assigned yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($active && $availableSuppliers->isNotEmpty())
                    <details class="group border-t border-stone-100" @if ($errors->has('supplier_ids')) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-2 px-5 py-3 text-sm font-medium text-brand-700 hover:bg-brand-50/50">
                            <x-icon name="plus" class="size-4 transition group-open:rotate-45" /> Assign more suppliers
                        </summary>
                        <form method="POST" action="{{ route('bookings.suppliers.store', $booking) }}" class="space-y-4 px-5 pb-5">
                            @csrf
                            @foreach ($availableSuppliers->groupBy('category') as $category => $group)
                                <div>
                                    <div class="mb-1.5 flex items-baseline justify-between gap-3">
                                        <span class="text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $category }}</span>
                                        @if ($budgetByCategory[$category]['allocated'] ?? null)
                                            <span class="text-xs text-stone-500">Client budget ₱{{ number_format($budgetByCategory[$category]['allocated'], 0) }}</span>
                                        @endif
                                    </div>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($group as $s)
                                            @php $preferred = $booking->preferences->contains('supplier_id', $s->id); @endphp
                                            <label class="check-chip">
                                                <input type="checkbox" name="supplier_ids[]" value="{{ $s->id }}" @checked($preferred) class="rounded border-stone-300 text-brand-600">
                                                <span class="flex-1">{{ $s->name }}</span>
                                                @if ($preferred)<span class="badge-brand">★ Client pick</span>@endif
                                                @if ($s->availability === 'unavailable')<span class="badge-gray">Unavailable</span>@endif
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between gap-3">
                                <p class="hint mt-0">The client's preferred suppliers are pre-ticked.</p>
                                <button class="btn-primary">Assign selected</button>
                            </div>
                        </form>
                    </details>
                @endif
            </div>

            {{-- Set-up items: vendor products chosen for this wedding --}}
            @php
                $items = $booking->bookingProducts;
                $itemsTotal = $items->sum(fn ($i) => $i->subtotal() ?? 0);
                $preferredSupplierIds = $booking->preferences->pluck('supplier_id');
                $overallLeft = $budget ? round((float) $budget->total_amount - $itemsTotal, 2) : null;
                $categoryLeft = $budgetByCategory->whereNotNull('remaining')->map(fn ($row) => $row['remaining']);
                // A product doesn't fit if one unit costs more than what's left in its category or overall.
                $fits = fn ($p) => $p->price === null || collect([$categoryLeft->get($p->supplier->category), $overallLeft])
                    ->filter(fn ($left) => $left !== null)->every(fn ($left) => (float) $p->price <= $left);
            @endphp
            <div class="card scroll-mt-6" id="setup">
                <div class="card-header">
                    <h2 class="card-title">Set-up items</h2>
                    <span class="text-xs text-stone-500">{{ $items->count() }} item(s) · est. ₱{{ number_format($itemsTotal, 2) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Item</th><th>Supplier</th><th>Supplier deadline</th><th class="text-right">Qty</th><th class="text-right">Subtotal</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($items as $item)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <x-product-image :product="$item->product" class="size-10 shrink-0" />
                                            <div>
                                                <div class="font-medium">{{ $item->product->name }}</div>
                                                @if ($item->product->is_package)
                                                    <span class="badge-gold">Package · {{ $item->product->guestLabel() }}</span>
                                                @endif
                                                <div class="text-xs text-stone-500">{{ $item->unit_price !== null ? '₱'.number_format($item->unit_price, 2).' '.$item->product->unit : 'Price on request' }}{{ $item->notes ? ' · '.$item->notes : '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $item->product->supplier->name }}<div class="text-xs text-stone-500">{{ $item->product->supplier->category }}</div></td>
                                    <td class="whitespace-nowrap">
                                        @if ($active)
                                            <form method="POST" action="{{ route('bookings.items.update', [$booking, $item]) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="quantity" value="{{ $item->quantity }}">
                                                <input type="hidden" name="notes" value="{{ $item->notes }}">
                                                <input name="due_date" type="date" required max="{{ $booking->event_date->toDateString() }}" value="{{ $item->due_date?->toDateString() }}" class="input w-36 py-1 text-xs" aria-label="Ready-by date for {{ $item->product->name }}" onchange="this.form.submit()">
                                            </form>
                                        @else
                                            {{ $item->due_date?->format('M j, Y') ?? '—' }}
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if ($active)
                                            <form method="POST" action="{{ route('bookings.items.update', [$booking, $item]) }}" class="flex justify-end">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="notes" value="{{ $item->notes }}">
                                                <input name="quantity" type="number" min="1" value="{{ $item->quantity }}" class="input w-20 py-1 text-right text-xs" aria-label="Quantity" onchange="this.form.submit()">
                                            </form>
                                        @else
                                            {{ $item->quantity }}
                                        @endif
                                    </td>
                                    <td class="text-right tabular-nums">{{ $item->subtotal() !== null ? '₱'.number_format($item->subtotal(), 2) : '—' }}</td>
                                    <td class="text-right">
                                        @if ($active)
                                            <form method="POST" action="{{ route('bookings.items.destroy', [$booking, $item]) }}" data-confirm="Remove {{ $item->product->name }} from the set-up?">
                                                @csrf @method('DELETE')
                                                <button class="btn-icon" title="Remove {{ $item->product->name }}" aria-label="Remove {{ $item->product->name }}"><x-icon name="trash" class="size-4" /></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-stone-500">No vendor products selected yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($active)
                    <form method="POST" action="{{ route('bookings.items.store', $booking) }}" class="flex flex-wrap items-end gap-3 border-t border-stone-100 p-4"
                        @if ($budget) data-setup-budget="{{ json_encode(['overall' => $overallLeft, 'categories' => $categoryLeft]) }}" @endif>
                        @csrf
                        @php $productSuppliers = $selectableProducts->pluck('supplier')->unique('id')->sortBy(['category', 'name']); @endphp
                        <div class="min-w-48">
                            <label class="label" for="product_supplier">Supplier</label>
                            <select id="product_supplier" class="input" data-supplier-filter>
                                <option value="">All suppliers ({{ $productSuppliers->count() }})</option>
                                @foreach ($productSuppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} · {{ $s->category }}{{ $preferredSupplierIds->contains($s->id) ? ' ★' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-60 flex-1">
                            <label class="label" for="supplier_product_id">Product</label>
                            <select id="supplier_product_id" name="supplier_product_id" required class="input">
                                <option value="">{{ $selectableProducts->isEmpty() ? 'No supplier has listed products yet' : 'Choose a product from a supplier…' }}</option>
                                @foreach ($selectableProducts->groupBy(fn ($p) => $p->supplier->category.' · '.$p->supplier->name.($preferredSupplierIds->contains($p->supplier_id) ? ' ★ client preference' : '')
                                    .($categoryLeft->has($p->supplier->category) ? ' · ₱'.number_format(max(0, $categoryLeft[$p->supplier->category]), 0).' left in budget' : '')) as $group => $products)
                                    <optgroup label="{{ $group }}" data-supplier="{{ $products->first()->supplier_id }}">
                                        @foreach ($products as $p)
                                            @php $left = $p->unitsLeft(); @endphp
                                            <option value="{{ $p->id }}" data-price="{{ $p->price }}" data-category="{{ $p->supplier->category }}" @disabled($left === 0)>{{ $p->is_package ? 'Package for '.number_format($p->guest_capacity).' guests: ' : '' }}{{ $p->name }} — {{ $p->supplier->name }} ({{ $p->priceLabel() }}){{ $fits($p) ? '' : ' · over budget' }}{{ $left === null ? '' : ($left === 0 ? ' · out of stock' : ' · '.$left.' left') }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="quantity">Qty</label>
                            <input id="quantity" name="quantity" type="number" min="1" value="1" required class="input w-20">
                        </div>
                        <div>
                            <label class="label" for="due_date">Supplier deadline</label>
                            <input id="due_date" name="due_date" type="date" required min="{{ today()->toDateString() }}" max="{{ $booking->event_date->toDateString() }}" value="{{ old('due_date', \App\Models\BookingProduct::suggestedDueDate($booking)->toDateString()) }}" class="input w-40">
                        </div>
                        <div class="min-w-40 flex-1">
                            <label class="label" for="item_notes">Notes</label>
                            <input id="item_notes" name="notes" maxlength="255" class="input" placeholder="e.g. blush & ivory">
                        </div>
                        <button class="btn-primary">Add</button>
                        @if ($budget)
                            <p class="hidden w-full rounded-lg px-3 py-2 text-xs ring-1" data-setup-hint></p>
                        @endif
                        <p class="hint w-full">Set when the supplier must have the product ready. They see this deadline in their portal so they can prepare the order. Adding a product also assigns its vendor to this booking. The estimate is for planning only. The contract amount is set separately.</p>
                    </form>
                @endif
            </div>

            {{-- Tasks (UC-05) --}}
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Tasks</h2>
                    @if ($active)
                        <a href="{{ route('tasks.create', ['booking_id' => $booking->id]) }}" class="btn-secondary btn-sm"><x-icon name="plus" class="size-4" /> Add task</a>
                    @endif
                </div>
                <ul class="divide-y divide-stone-100">
                    @forelse ($booking->tasks as $t)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div>
                                <div @class(['font-medium', 'text-stone-400 line-through' => $t->status === 'completed'])>{{ $t->title }}</div>
                                <div class="text-xs text-stone-500">Due {{ $t->due_date->format('M j, Y') }} · {{ $t->assignee?->name }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($t->is_overdue && $t->status !== 'completed')<span class="badge-red">Overdue</span>@endif
                                <span class="badge-{{ \App\Models\Task::priorityBadge($t->priority) }}">{{ ucfirst($t->priority) }}</span>
                                <form method="POST" action="{{ route('tasks.status', $t) }}">
                                    @csrf @method('PATCH')
                                    <select name="status" class="input py-1 text-xs" data-autosubmit aria-label="Task status">
                                        @foreach (\App\Models\Task::STATUSES as $s)
                                            <option value="{{ $s }}" @selected($t->status === $s)>{{ ucfirst($s) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-stone-500">No tasks for this wedding yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-6">
            {{-- The couple's own budget, compared with the contract and selected set-up items --}}
            @php
                $setupEstimate = $booking->bookingProducts->sum(fn ($i) => $i->subtotal() ?? 0);
            @endphp
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Client budget</h2>
                    @if ($budget)<span class="text-xs text-stone-500">updated {{ $budget->updated_at->diffForHumans() }}</span>@endif
                </div>
                @if (! $budget)
                    <div class="card-body text-sm text-stone-500">
                        The couple hasn't set a budget yet. They can add it under <strong>My Budget</strong> when they log in.
                    </div>
                @else
                    @php
                        $limit = (float) $budget->total_amount;
                        $rows = [
                            ['Contract amount', (float) $booking->total_amount],
                            ['Selected set-up items', $setupEstimate],
                        ];
                    @endphp
                    <div class="card-body space-y-4 text-sm">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <div class="text-[11px] font-semibold tracking-[0.14em] text-gold-700 uppercase">Total budget</div>
                                <div class="font-display text-3xl font-semibold tabular-nums">₱{{ number_format($limit, 0) }}</div>
                            </div>
                            @if ($budget->guest_count)
                                <div class="text-right text-xs text-stone-500">
                                    <div class="text-base font-semibold text-ink">{{ number_format($budget->guest_count) }} guests</div>
                                    ≈ ₱{{ number_format($budget->perGuest(), 0) }} per guest
                                </div>
                            @endif
                        </div>

                        <div class="space-y-2.5">
                            @foreach ($rows as [$label, $amount])
                                @php $pct = $limit > 0 ? $amount / $limit * 100 : 0; @endphp
                                <div>
                                    <div class="flex justify-between text-xs">
                                        <span class="text-stone-600">{{ $label }}</span>
                                        <span @class(['font-semibold tabular-nums', 'text-red-600' => $amount > $limit])>₱{{ number_format($amount, 0) }} · {{ round($pct) }}%</span>
                                    </div>
                                    <div class="mt-1 h-1.5 rounded-full bg-stone-100">
                                        <div @class(['h-1.5 rounded-full', 'bg-red-500' => $amount > $limit, 'bg-gold-400' => $amount <= $limit]) style="width: {{ min(100, $pct) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ((float) $booking->total_amount > $limit)
                            <p class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700 ring-1 ring-red-200">The contract is ₱{{ number_format((float) $booking->total_amount - $limit, 0) }} over the couple's budget.</p>
                        @elseif ($setupEstimate > $limit)
                            <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200">Selected items are ₱{{ number_format($setupEstimate - $limit, 0) }} over the couple's budget.</p>
                        @endif

                        @if ($budgetByCategory->isNotEmpty())
                            <div>
                                <div class="mb-2 text-[11px] font-semibold tracking-[0.14em] text-gold-700 uppercase">By category · picked vs. budget</div>
                                <ul class="space-y-2.5 text-xs">
                                    @foreach ($budgetByCategory as $row)
                                        @php $over = $row['remaining'] !== null && $row['remaining'] < 0; @endphp
                                        <li>
                                            <div class="flex justify-between gap-3">
                                                <span class="text-stone-600">{{ $row['category'] }}</span>
                                                <span @class(['tabular-nums', 'font-semibold text-red-600' => $over])>
                                                    ₱{{ number_format($row['selected'], 0) }}
                                                    <span class="font-normal text-stone-400">/ {{ $row['allocated'] !== null ? '₱'.number_format($row['allocated'], 0) : 'no budget set' }}</span>
                                                </span>
                                            </div>
                                            @if ($row['allocated'])
                                                <div class="mt-1 h-1.5 rounded-full bg-stone-100">
                                                    <div @class(['h-1.5 rounded-full', 'bg-red-500' => $over, 'bg-gold-400' => ! $over]) style="width: {{ min(100, $row['selected'] / $row['allocated'] * 100) }}%"></div>
                                                </div>
                                                <div @class(['mt-0.5 text-right', 'text-red-600' => $over, 'text-stone-500' => ! $over])>
                                                    {{ $over ? '₱'.number_format(-$row['remaining'], 0).' over' : '₱'.number_format($row['remaining'], 0).' left' }}
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                @if ($budget->unallocated() > 0)
                                    <p class="mt-2 text-xs text-stone-500">₱{{ number_format($budget->unallocated(), 0) }} of their budget isn't assigned to a category.</p>
                                @endif
                            </div>
                        @endif

                        @if ($budget->notes)
                            <blockquote class="rounded-lg bg-gold-50 px-3 py-2 text-xs text-stone-700 ring-1 ring-gold-200">
                                <span class="font-semibold text-gold-700">Couple's note:</span> {{ $budget->notes }}
                            </blockquote>
                        @endif
                    </div>
                @endif
            </div>

            {{-- QR code --}}
            <div class="card">
                <div class="card-header"><h2 class="card-title">Client QR status code</h2></div>
                <div class="card-body text-center">
                    @if ($qrSvg)
                        <div class="mx-auto inline-block rounded-xl border border-stone-200 bg-white p-3">{!! $qrSvg !!}</div>
                        <p class="mt-2 text-xs text-stone-500">Scanning requires a one-time PIN sent to {{ $booking->client->email }}.</p>
                        <div class="mt-3 flex justify-center gap-2">
                            <a href="{{ route('bookings.qr', $booking) }}" class="btn-secondary btn-sm"><x-icon name="printer" class="size-4" /> Print</a>
                            <a href="{{ route('bookings.qr.download', $booking) }}" class="btn-secondary btn-sm"><x-icon name="download" class="size-4" /> Download</a>
                        </div>
                    @elseif ($booking->status === 'pending')
                        <p class="text-sm text-stone-500">The QR code is generated when the booking is confirmed.</p>
                    @else
                        <p class="text-sm text-stone-500">QR access is not available for {{ $booking->status }} bookings.</p>
                    @endif
                </div>
            </div>

            {{-- Details --}}
            <div class="card">
                <div class="card-header"><h2 class="card-title">Details</h2></div>
                <dl class="card-body space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-stone-500">Package</dt><dd class="text-right font-medium">{{ $booking->package }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-stone-500">Planner</dt><dd class="text-right">{{ $booking->planner->name }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-stone-500">Client account</dt><dd class="text-right">{{ $booking->client->name }}<br><span class="text-xs text-stone-500">{{ $booking->client->email }}</span></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-stone-500">Contact</dt><dd class="text-right">{{ $booking->contact_number }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-stone-500">Created</dt><dd class="text-right">{{ $booking->created_at->format('M j, Y') }}</dd></div>
                </dl>
            </div>

            {{-- Client preferences --}}
            <div class="card">
                <div class="card-header"><h2 class="card-title">Client supplier preferences</h2></div>
                <ul class="divide-y divide-stone-100 text-sm">
                    @forelse ($booking->preferences as $p)
                        <li class="flex items-center justify-between px-5 py-2.5">
                            <span>{{ $p->supplier->name }} <span class="text-xs text-stone-500">· {{ $p->supplier->category }}</span></span>
                            @if ($booking->bookingSuppliers->contains('supplier_id', $p->supplier_id))
                                <span class="badge-green">Assigned</span>
                            @else
                                <span class="badge-amber">To review</span>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-5 text-center text-stone-500">The client hasn't indicated preferences yet.</li>
                    @endforelse
                </ul>
            </div>

            {{-- Payments --}}
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Payments</h2>
                    <a href="{{ route('payments.index', ['booking_id' => $booking->id]) }}" class="text-sm link">Record</a>
                </div>
                <ul class="divide-y divide-stone-100 text-sm">
                    @forelse ($booking->payments as $p)
                        <li class="flex items-center justify-between px-5 py-2.5">
                            <span>{{ $p->payment_date->format('M j, Y') }} <span class="text-xs text-stone-500">· {{ $p->methodLabel() }}</span></span>
                            <span class="font-medium tabular-nums">₱{{ number_format($p->amount, 2) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-5 text-center text-stone-500">No payments recorded.</li>
                    @endforelse
                </ul>
                <div class="border-t border-stone-100 px-5 py-3">
                    <a href="{{ route('bookings.contract', $booking) }}" class="btn-secondary btn-sm w-full"><x-icon name="download" class="size-4" /> Contract summary (PDF)</a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
