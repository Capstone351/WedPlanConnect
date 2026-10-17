<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingProduct;
use App\Models\BookingSupplier;
use App\Models\SupplierProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Planner selects vendor products for a wedding's set-up. Selecting a product also
 * assigns its supplier to the booking so coordination status is tracked as usual.
 */
class BookingProductController extends Controller
{
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);
        abort_unless(in_array($booking->status, Booking::ACTIVE_STATUSES), 403, 'Set-up items can only be changed on pending or confirmed bookings.');

        $data = $request->validate([
            'supplier_product_id' => ['required', Rule::exists('supplier_products', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'due_date' => ['required', 'after_or_equal:today', ...$this->dueDateRules($booking)],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['supplier_product_id.required' => 'Choose a product to add.'] + $this->dueDateMessages());

        $product = SupplierProduct::selectable()->findOrFail($data['supplier_product_id']);

        $item = BookingProduct::firstOrNew(['booking_id' => $booking->id, 'supplier_product_id' => $product->id]);
        $item->fill([
            'quantity' => $data['quantity'],
            'due_date' => $data['due_date'],
            'notes' => $data['notes'] ?? null,
            'unit_price' => $item->exists ? $item->unit_price : $product->price,
        ])->save();

        $this->ensureSupplierAssigned($booking, $product->supplier_id);

        return back()->with('status', "{$product->name} (×{$item->quantity}) added to this wedding's set-up.");
    }

    public function update(Request $request, Booking $booking, BookingProduct $item): RedirectResponse
    {
        $this->authorizeBooking($booking);
        abort_unless($item->booking_id === $booking->id, 404);

        $item->update($request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'due_date' => ['sometimes', 'required', ...$this->dueDateRules($booking)],
            'notes' => ['nullable', 'string', 'max:255'],
        ], $this->dueDateMessages()));

        return back()->with('status', "{$item->product->name} updated.");
    }

    public function destroy(Booking $booking, BookingProduct $item): RedirectResponse
    {
        $this->authorizeBooking($booking);
        abort_unless($item->booking_id === $booking->id, 404);

        $item->delete();

        return back()->with('status', "{$item->product->name} removed from the set-up.");
    }

    /** The vendor's "ready by" date can be no later than the wedding itself. */
    private function dueDateRules(Booking $booking): array
    {
        return ['date', 'before_or_equal:'.$booking->event_date->toDateString()];
    }

    private function dueDateMessages(): array
    {
        return [
            'due_date.required' => 'Set a ready-by date so the supplier knows when to have the product ready.',
            'due_date.after_or_equal' => 'The ready-by date cannot be in the past.',
            'due_date.before_or_equal' => 'The ready-by date cannot be after the wedding date.',
        ];
    }

    private function ensureSupplierAssigned(Booking $booking, int $supplierId): void
    {
        $assignment = BookingSupplier::withTrashed()->firstOrNew(['booking_id' => $booking->id, 'supplier_id' => $supplierId]);

        if ($assignment->trashed()) {
            $assignment->restore();
        }

        if (! $assignment->exists) {
            $assignment->status = 'pending';
            $assignment->save();
        }
    }
}
