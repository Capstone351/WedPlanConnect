<x-layouts.app title="Edit Payment" heading="Edit Payment" :subheading="'Booking #'.$payment->booking_id.' · '.$payment->booking->client_name">
    <form method="POST" action="{{ route('payments.update', $payment) }}" class="card max-w-xl">
        @csrf @method('PUT')
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <div>
                <label for="amount" class="label">Amount (₱)</label>
                <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', $payment->amount) }}" required class="input">
            </div>
            <div>
                <label for="method" class="label">Method</label>
                <select id="method" name="method" class="input">
                    @foreach (\App\Models\Payment::METHODS as $k => $v)<option value="{{ $k }}" @selected(old('method', $payment->method) === $k)>{{ $v }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="payment_date" class="label">Date</label>
                <input id="payment_date" name="payment_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('payment_date', $payment->payment_date->toDateString()) }}" required class="input">
            </div>
            <div>
                <label for="receipt_number" class="label">Receipt #</label>
                <input id="receipt_number" name="receipt_number" value="{{ old('receipt_number', $payment->receipt_number) }}" maxlength="50" class="input">
            </div>
            <div class="sm:col-span-2">
                <label for="notes" class="label">Notes</label>
                <textarea id="notes" name="notes" rows="2" maxlength="2000" class="input">{{ old('notes', $payment->notes) }}</textarea>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-4">
            <a href="{{ route('payments.index', ['booking_id' => $payment->booking_id]) }}" class="btn-secondary">Cancel</a>
            <button class="btn-primary">Save changes</button>
        </div>
    </form>
</x-layouts.app>
