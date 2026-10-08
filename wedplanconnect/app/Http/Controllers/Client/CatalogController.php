<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Supplier;
use App\Models\SupplierPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * UC-07 client side: browse the catalog (contact details hidden) and indicate preferred suppliers.
 * Final coordination stays with the Wedding Planner.
 */
class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $booking = $this->activeBooking($request);

        $suppliers = Supplier::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->withCount(['products as offerings_count' => fn ($q) => $q->where('is_available', true)])
            ->orderBy('category')->orderBy('name')
            ->get(['id', 'name', 'category', 'description', 'starting_price', 'availability']);

        return view('client.catalog', [
            'suppliers' => $suppliers,
            'categories' => Supplier::distinct()->orderBy('category')->pluck('category'),
            'booking' => $booking,
            'preferred' => $booking ? $booking->preferences()->pluck('id', 'supplier_id') : collect(),
        ]);
    }

    /** A supplier's profile and offerings. Contact details are never shown to clients. */
    public function show(Request $request, Supplier $supplier): View
    {
        $booking = $this->activeBooking($request);

        return view('client.supplier', [
            'supplier' => $supplier,
            'products' => $supplier->products()->where('is_available', true)->orderBy('name')->get(),
            'booking' => $booking,
            'preferenceId' => $booking?->preferences()->where('supplier_id', $supplier->id)->value('id'),
            'chosen' => $booking
                ? $booking->bookingProducts()->whereHas('product', fn ($q) => $q->where('supplier_id', $supplier->id))->pluck('quantity', 'supplier_product_id')
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $booking = $this->activeBooking($request);

        if (! $booking) {
            return back()->withErrors(['supplier_id' => 'Your planner needs to create your wedding booking before you can save preferences.']);
        }

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $preference = SupplierPreference::firstOrCreate(
            ['booking_id' => $booking->id, 'supplier_id' => $data['supplier_id']],
            ['notes' => $data['notes'] ?? null]
        );

        return back()->with('status', "{$preference->supplier->name} added to your preferred suppliers. Your planner will review it.");
    }

    public function destroy(Request $request, SupplierPreference $preference): RedirectResponse
    {
        abort_unless($preference->booking->client_id === $request->user()->id, 403);
        $preference->delete();

        return back()->with('status', 'Preference removed.');
    }

    /** The couple's active booking; staff previewing the catalog have none, so they can't save favorites. */
    private function activeBooking(Request $request): ?Booking
    {
        if ($request->user()->role !== 'client') {
            return null;
        }

        return $request->user()->clientBookings()->active()->orderBy('event_date')->first();
    }
}
