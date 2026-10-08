@php
    $paid = $booking->payments->sum('amount');
    $balance = (float) $booking->total_amount - $paid;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { font-size: 11px; color: #2b2226; }
        .brand { text-align: center; border-bottom: 2px solid #edd0d7; padding-bottom: 12px; }
        .brand .name { font-size: 20px; font-weight: bold; color: #83364c; }
        .brand .sub { font-size: 9px; color: #78716c; letter-spacing: 1px; }
        h2 { font-size: 13px; color: #83364c; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 4px 0; vertical-align: top; }
        .info td.k { width: 32%; color: #78716c; }
        .list th { background: #fbf5f6; text-align: left; padding: 6px; font-size: 9px; text-transform: uppercase; color: #6d3042; border-bottom: 1px solid #edd0d7; }
        .list td { padding: 6px; border-bottom: 1px solid #f5f5f4; }
        .right { text-align: right; }
        .totals td { padding: 5px 6px; }
        .totals .grand td { font-weight: bold; font-size: 13px; border-top: 2px solid #83364c; }
        .sign { margin-top: 50px; }
        .sign td { width: 50%; padding: 0 20px; text-align: center; font-size: 10px; }
        .line { border-top: 1px solid #2b2226; margin-top: 40px; padding-top: 4px; }
        .note { margin-top: 20px; font-size: 8px; color: #a8a29e; }
    </style>
</head>
<body>
    <div class="brand">
        <div class="name">{{ config('wedplan.business_name') }}</div>
        <div class="sub">{{ config('wedplan.business_address') }} · CONTRACT & PAYMENT SUMMARY</div>
    </div>

    <h2>Booking information</h2>
    <table class="info">
        <tr><td class="k">Booking reference</td><td>#{{ $booking->id }}</td></tr>
        <tr><td class="k">Client</td><td>{{ $booking->client_name }}</td></tr>
        <tr><td class="k">Contact number</td><td>{{ $booking->contact_number }}</td></tr>
        <tr><td class="k">Event date</td><td>{{ $booking->event_date->format('l, F j, Y') }}</td></tr>
        <tr><td class="k">Venue</td><td>{{ $booking->venue }}</td></tr>
        <tr><td class="k">Package</td><td>{{ $booking->package }}</td></tr>
        <tr><td class="k">Wedding planner</td><td>{{ $booking->planner->name }}</td></tr>
        <tr><td class="k">Booking status</td><td>{{ ucfirst($booking->status) }}</td></tr>
    </table>

    @if ($booking->suppliers->isNotEmpty())
        <h2>Coordinated suppliers</h2>
        <table class="list">
            <thead><tr><th>Supplier</th><th>Category</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($booking->suppliers as $s)
                    <tr><td>{{ $s->name }}</td><td>{{ $s->category }}</td><td>{{ ucfirst($s->pivot->status) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($booking->bookingProducts->isNotEmpty())
        <h2>Set-up items</h2>
        <table class="list">
            <thead><tr><th>Item</th><th>Supplier</th><th class="right">Qty</th><th class="right">Unit price</th><th class="right">Subtotal</th></tr></thead>
            <tbody>
                @foreach ($booking->bookingProducts as $item)
                    <tr>
                        <td>{{ $item->product->name }}{{ $item->notes ? ' ('.$item->notes.')' : '' }}</td>
                        <td>{{ $item->product->supplier->name }}</td>
                        <td class="right">{{ $item->quantity }}</td>
                        <td class="right">{{ $item->unit_price !== null ? '₱'.number_format($item->unit_price, 2) : 'On request' }}</td>
                        <td class="right">{{ $item->subtotal() !== null ? '₱'.number_format($item->subtotal(), 2) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="font-size:8px;color:#78716c">Item prices are estimates for planning. The contract amount below governs.</p>
    @endif

    <h2>Payment history</h2>
    <table class="list">
        <thead><tr><th>Date</th><th>Method</th><th>Receipt #</th><th>Notes</th><th class="right">Amount</th></tr></thead>
        <tbody>
            @forelse ($booking->payments as $p)
                <tr>
                    <td>{{ $p->payment_date->format('M j, Y') }}</td>
                    <td>{{ $p->methodLabel() }}</td>
                    <td>{{ $p->receipt_number ?: '—' }}</td>
                    <td>{{ $p->notes }}</td>
                    <td class="right">₱{{ number_format($p->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;color:#78716c">No payments recorded.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals" style="margin-top:10px">
        <tr><td class="right">Contract amount</td><td class="right" style="width:25%">₱{{ number_format($booking->total_amount, 2) }}</td></tr>
        <tr><td class="right">Total paid</td><td class="right">₱{{ number_format($paid, 2) }}</td></tr>
        <tr class="grand"><td class="right">Remaining balance</td><td class="right">₱{{ number_format($balance, 2) }}</td></tr>
    </table>

    <table class="sign">
        <tr>
            <td><div class="line">{{ $booking->client_name }}<br>Client</div></td>
            <td><div class="line">{{ $booking->planner->name }}<br>for {{ config('wedplan.business_name') }}</div></td>
        </tr>
    </table>

    <div class="note">Generated {{ now()->format('F j, Y g:i A') }} via WedPlanConnect. Payments are recorded manually; this document is not an official receipt. Personal data herein is protected under the Data Privacy Act of 2012 (RA 10173).</div>
</body>
</html>
