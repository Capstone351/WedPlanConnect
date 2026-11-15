<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingSupplier;
use App\Notifications\SupplierBookingUpdated;
use App\Notifications\SupplierOrderSent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * UC-04 Manage Supplier Selection: assign suppliers to a booking and track coordination status.
 */
class BookingSupplierController extends Controller
{
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);
        abort_if($booking->status === 'cancelled', 403, 'Suppliers cannot be assigned to a cancelled booking.');

        $data = $request->validate([
            'supplier_ids' => ['required', 'array', 'min:1'],
            'supplier_ids.*' => ['integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
        ], ['supplier_ids.required' => 'Select at least one supplier to assign.']);

        $notified = [];
        $unreachable = [];

        foreach ($data['supplier_ids'] as $supplierId) {
            $assignment = BookingSupplier::withTrashed()->firstOrNew([
                'booking_id' => $booking->id,
                'supplier_id' => $supplierId,
            ]);
            $isNew = ! $assignment->exists || $assignment->trashed();

            if ($assignment->trashed()) {
                $assignment->restore();
            }

            $assignment->status ??= 'pending';
            $assignment->save();

            // A newly assigned supplier is sent their order right away, so they hear about the wedding.
            if ($isNew) {
                $this->sendOrder($booking, $assignment) ? $notified[] = $assignment->supplier->name : $unreachable[] = $assignment->supplier->name;
            }
        }

        return back()->with('status', count($data['supplier_ids']).' supplier(s) assigned to this booking.'
            .($notified ? ' Order sent to '.Arr::join($notified, ', ', ' and ').'.' : '')
            .($unreachable ? ' '.Arr::join($unreachable, ', ', ' and ').' '.(count($unreachable) > 1 ? 'have' : 'has').' no vendor account or email, so contact them directly.' : ''));
    }

    public function update(Request $request, Booking $booking, BookingSupplier $assignment): RedirectResponse
    {
        $this->authorizeBooking($booking);
        abort_unless($assignment->booking_id === $booking->id, 404);

        $data = $request->validate(['status' => ['required', Rule::in(BookingSupplier::STATUSES)]]);
        $assignment->update($data);

        return back()->with('status', "{$assignment->supplier->name} marked as ".ucfirst($data['status']).'.');
    }

    /** Sends the supplier their order (services plus any products picked from them) to accept or decline. */
    public function send(Booking $booking, BookingSupplier $assignment): RedirectResponse
    {
        $this->authorizeBooking($booking);
        abort_unless($assignment->booking_id === $booking->id, 404);
        abort_unless(in_array($booking->status, Booking::ACTIVE_STATUSES), 403, 'Orders can only be sent for pending or confirmed bookings.');

        $supplier = $assignment->supplier;
        if (! $this->sendOrder($booking, $assignment)) {
            return back()->withErrors(['order' => "{$supplier->name} has no vendor account or email, so the order can't be sent. Contact them directly and update their status by hand."]);
        }

        return back()->with('status', "Order sent to {$supplier->name}. You'll be notified when they accept or decline.");
    }

    public function destroy(Booking $booking, BookingSupplier $assignment): RedirectResponse
    {
        $this->authorizeBooking($booking);
        abort_unless($assignment->booking_id === $booking->id, 404);

        $assignment->delete();

        // Only a supplier who was told about the wedding needs to hear they're off it.
        if ($assignment->order_sent_at || $assignment->status === 'confirmed') {
            $assignment->supplier->sendNotification(new SupplierBookingUpdated($booking, 'Removed from a wedding',
                "{$booking->planner->name} removed you from the {$booking->client_name} wedding on {$booking->event_date->format('F j, Y')}. Your services are no longer needed for it.", false));
        }

        return back()->with('status', "{$assignment->supplier->name} removed from this booking.");
    }

    /**
     * Sends the supplier their order (services plus any products picked from them) to accept or decline.
     * Returns false when the supplier has no vendor login or email to send it to.
     */
    private function sendOrder(Booking $booking, BookingSupplier $assignment): bool
    {
        $supplier = $assignment->supplier;
        if (! $supplier->isReachable()) {
            return false;
        }

        $items = $booking->bookingProducts()->with('product')
            ->whereHas('product', fn ($q) => $q->withTrashed()->where('supplier_id', $supplier->id))
            ->orderBy('due_date')->get();

        $assignment->markOrderSent();

        return $supplier->sendNotification(new SupplierOrderSent($assignment->load('booking.planner', 'supplier'), $items));
    }
}
