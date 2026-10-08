<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * UC-08 Record Client Payments and Monitor Contract Amounts (manual recording, no payment gateway).
 */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $bookings = Booking::visibleTo($user)
            ->where('status', '!=', 'cancelled')
            ->withSum('payments', 'amount')
            ->when($request->filled('q'), fn ($q) => $q->where('client_name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('event_date')
            ->get();

        $filter = $request->input('payment_status');
        if ($filter) {
            $bookings = $bookings->filter(fn ($b) => $this->statusOf($b) === $filter)->values();
        }

        $selected = $request->filled('booking_id')
            ? Booking::visibleTo($user)->with(['payments' => fn ($q) => $q->orderByDesc('payment_date')])->find($request->integer('booking_id'))
            : null;

        return view('payments.index', [
            'bookings' => $bookings,
            'selected' => $selected,
            'allBookings' => Booking::visibleTo($user)->where('status', '!=', 'cancelled')->orderBy('event_date')->get(['id', 'client_name', 'event_date', 'total_amount']),
            'totals' => [
                'contract' => (float) $bookings->sum('total_amount'),
                'collected' => (float) $bookings->sum('payments_sum_amount'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $booking = Booking::findOrFail($data['booking_id']);
        $this->authorizeBooking($booking);
        $this->guardOverpayment($booking, (float) $data['amount']);

        Payment::create($data);

        return redirect()->route('payments.index', ['booking_id' => $booking->id])
            ->with('status', 'Payment of ₱'.number_format($data['amount'], 2).' recorded. Remaining balance: ₱'.number_format($booking->balance(), 2).'.');
    }

    public function edit(Payment $payment): View
    {
        $this->authorizeBooking($payment->booking);

        return view('payments.edit', ['payment' => $payment]);
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizeBooking($payment->booking);
        $data = $this->validated($request, $payment);
        $this->guardOverpayment($payment->booking, (float) $data['amount'], $payment);

        $payment->update($data);

        return redirect()->route('payments.index', ['booking_id' => $payment->booking_id])->with('status', 'Payment updated; balance recalculated.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorizeBooking($payment->booking);
        $payment->delete();

        return back()->with('status', 'Payment voided; balance recalculated.');
    }

    /** PDF contract / payment summary for a booking. */
    public function contract(Booking $booking): Response
    {
        $this->authorizeBooking($booking);
        $booking->load(['payments' => fn ($q) => $q->orderBy('payment_date'), 'suppliers', 'planner', 'bookingProducts.product.supplier']);

        return Pdf::loadView('pdf.contract', ['booking' => $booking])
            ->setPaper('a4')
            ->download('contract-summary-booking-'.$booking->id.'.pdf');
    }

    private function validated(Request $request, ?Payment $payment = null): array
    {
        return $request->validate([
            'booking_id' => [$payment ? 'prohibited' : 'required', Rule::exists('bookings', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'receipt_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['payment_date.before_or_equal' => 'The payment date cannot be in the future.']);
    }

    /** Flags discrepancies: recorded payments may never exceed the contract amount. */
    private function guardOverpayment(Booking $booking, float $amount, ?Payment $existing = null): void
    {
        if ($booking->status === 'cancelled') {
            throw ValidationException::withMessages(['booking_id' => 'Payments cannot be recorded for a cancelled booking.']);
        }

        $balance = $booking->balance() + ($existing ? (float) $existing->amount : 0);

        if ($amount - $balance > 0.001) {
            throw ValidationException::withMessages([
                'amount' => 'Payment exceeds the remaining balance of ₱'.number_format($balance, 2).' for this contract.',
            ]);
        }
    }

    private function statusOf(Booking $booking): string
    {
        $paid = (float) $booking->payments_sum_amount;

        return $paid <= 0 ? 'unpaid' : ($paid >= (float) $booking->total_amount ? 'paid' : 'partial');
    }
}
