<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingSupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function destroy(Booking $booking, BookingSupplier $assignment): RedirectResponse
    {
        $this->authorizeBooking($booking);
        abort_unless($assignment->booking_id === $booking->id, 404);

        $assignment->delete();

        return back()->with('status', "{$assignment->supplier->name} removed from this booking.");
    }
}
