<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\PaymentRecorded;
use App\Notifications\PaymentVoided;
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
            ? Booking::visibleTo($user)->with([
                'payments' => fn ($q) => $q->latestFirst()->with('recorder:id,name'),
                // Voided payments stay visible (crossed out) so nothing disappears from the record.
                'voidedPayments' => fn ($q) => $q->latestFirst()->with('recorder:id,name', 'voider:id,name'),
            ])->find($request->integer('booking_id'))
            : null;

        return view('payments.index', [
            'bookings' => $bookings,
            'selected' => $selected,
            'allBookings' => Booking::visibleTo($user)->whereIn('status', Booking::PAYABLE_STATUSES)->orderBy('event_date')->get(['id', 'client_name', 'event_date', 'total_amount']),
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

        // The date is always the day it's recorded, so it can't be back- or forward-dated.
        $payment = Payment::create([...$data, 'payment_date' => today(), 'recorded_by' => $request->user()->id]);

        // Payments can't be edited, and the couple gets their own record of each one.
        $booking->client?->notify(new PaymentRecorded($payment, $request->user()->name));

        return redirect()->route('payments.index', ['booking_id' => $booking->id])
            ->with('status', 'Payment of ₱'.number_format($data['amount'], 2).' recorded. Remaining balance: ₱'.number_format($booking->balance(), 2).'.');
    }

    /** Voids a payment (kept on record, crossed out) with a reason, and tells the couple. */
    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizeBooking($payment->booking);

        $data = $request->validate(['void_reason' => ['required', 'string', 'max:500']], [
            'void_reason.required' => 'Please give a reason for voiding this payment. The couple will see it.',
        ]);

        $payment->forceFill(['voided_by' => $request->user()->id, 'void_reason' => trim($data['void_reason'])])->save();
        $payment->delete();

        $payment->booking->client?->notify(new PaymentVoided($payment, $request->user()->name));

        return back()->with('status', 'Payment voided; balance recalculated. The couple has been notified.');
    }

    /** PDF contract / payment summary for a booking. */
    public function contract(Booking $booking): Response
    {
        $this->authorizeBooking($booking);
        $booking->load(['payments' => fn ($q) => $q->latestFirst(), 'suppliers', 'planner', 'bookingProducts.product.supplier']);

        return Pdf::loadView('pdf.contract', ['booking' => $booking])
            ->setPaper('a4')
            ->download('contract-summary-booking-'.$booking->id.'.pdf');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'booking_id' => ['required', Rule::exists('bookings', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'receipt_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /** Flags discrepancies: recorded payments may never exceed the contract amount. */
    private function guardOverpayment(Booking $booking, float $amount): void
    {
        if ($booking->status === 'cancelled') {
            throw ValidationException::withMessages(['booking_id' => 'Payments cannot be recorded for a cancelled booking.']);
        }

        // Money is only taken once the planner has confirmed the booking (and its price).
        if (! in_array($booking->status, Booking::PAYABLE_STATUSES, true)) {
            throw ValidationException::withMessages(['booking_id' => 'Confirm this booking first. Payments can only be recorded for confirmed bookings.']);
        }

        $balance = $booking->balance();

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
