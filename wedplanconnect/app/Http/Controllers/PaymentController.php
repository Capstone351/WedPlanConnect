<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentRecorded;
use App\Notifications\PaymentVoidRequested;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
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

    /**
     * Planners can't remove money they recorded. They ask the admin to void a payment, with a reason;
     * the admin approves or keeps it from their action centre.
     */
    public function requestVoid(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizeBooking($payment->booking);

        $data = $request->validate(['void_reason' => ['required', 'string', 'max:500']], [
            'void_reason.required' => 'Please say why this payment should be voided. The admin and, if it is voided, the couple will see it.',
        ]);

        if ($payment->hasVoidRequest()) {
            return back()->withErrors(['void_reason' => 'You already asked the admin to void this payment. Please wait for their answer.']);
        }

        $payment->forceFill([
            'void_requested_by' => $request->user()->id,
            'void_requested_at' => now(),
            'void_request_reason' => trim($data['void_reason']),
        ])->save();

        Notification::send(User::where('role', 'admin')->get(), new PaymentVoidRequested($payment->load('booking', 'voidRequester')));

        return back()->with('status', 'Request sent. The admin will void the payment or keep it, and you will be notified.');
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
