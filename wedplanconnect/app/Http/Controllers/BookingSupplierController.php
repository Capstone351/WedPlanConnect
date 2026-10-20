<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingSupplier;
use App\Notifications\SupplierOrderSent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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

        foreach ($data['supplier_ids'] as $supplierId) {
            $assignment = BookingSupplier::withTrashed()->firstOrNew([
                'booking_id' => $booking->id,
                'supplier_id' => $supplierId,
            ]);

            if ($assignment->trashed()) {
                $assignment->restore();
            }

            $assignment->status ??= 'pending';
            $assignment->save();
        }

        return back()->with('status', count($data['supplier_ids']).' supplier(s) assigned to this booking.');
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
        if (! $supplier->user && ! $supplier->email) {
            return back()->withErrors(['order' => "{$supplier->name} has no vendor account or email, so the order can't be sent. Contact them directly and update their status by hand."]);
        }

        $items = $booking->bookingProducts()->with('product')
            ->whereHas('product', fn ($q) => $q->withTrashed()->where('supplier_id', $supplier->id))
            ->orderBy('due_date')->get();

        $assignment->markOrderSent();
        $notification = new SupplierOrderSent($assignment->load('booking.planner', 'supplier'), $items);
        $supplier->user
            ? $supplier->user->notify($notification)
            : Notification::route('mail', $supplier->email)->notify($notification);

        return back()->with('status', "Order sent to {$supplier->name}. You'll be notified when they accept or decline.");
    }

    public function destroy(Booking $booking, BookingSupplier $assignment): RedirectResponse
    {
        $this->authorizeBooking($booking);
        abort_unless($assignment->booking_id === $booking->id, 404);

        $assignment->delete();

        return back()->with('status', "{$assignment->supplier->name} removed from this booking.");
    }
}
