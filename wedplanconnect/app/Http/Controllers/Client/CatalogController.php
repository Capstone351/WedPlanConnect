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
 * UC-07 client side: browse the catalog (contact details hidden) and save favorite suppliers.
 * Favorites belong to the couple's account, so they work before a booking exists.
 * Final coordination stays with the Wedding Planner.
 */
class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $preferred = $request->user()->favoriteSuppliers()->pluck('id', 'supplier_id');
        $guests = $request->integer('guests') ?: null;
        $availablePackages = fn ($q) => $q->where('is_available', true)->where('is_package', true);

        $suppliers = Supplier::query()
            ->when($request->boolean('favorites'), fn ($q) => $q->whereIn('id', $preferred->keys()))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            // "Packages for N guests or more": suppliers with an available package that serves at least N guests.
            ->when($guests, fn ($q) => $q->whereHas('products', fn ($p) => $p->where('is_available', true)->packagesFor($guests)))
            ->withCount([
                'products as offerings_count' => fn ($q) => $q->where('is_available', true),
                'products as packages_count' => $availablePackages,
            ])
            ->withMax(['products as max_package_guests' => $availablePackages], 'guest_capacity')
            ->orderBy('category')->orderBy('name')
            ->get(['id', 'name', 'category', 'description', 'starting_price', 'availability']);

        return view('client.catalog', [
            'suppliers' => $suppliers,
            'categories' => Supplier::distinct()->orderBy('category')->pluck('category'),
            'canFavorite' => $this->isClient($request),
            'preferred' => $preferred,
            'guests' => $guests,
            'clientGuests' => $this->clientGuestCount($request),
        ]);
    }

    /** A supplier's profile and offerings. Contact details are never shown to clients. */
    public function show(Request $request, Supplier $supplier): View
    {
        $booking = $this->activeBooking($request);

        return view('client.supplier', [
            'supplier' => $supplier,
            // Packages first, smallest to largest, then single products.
            'products' => $supplier->products()->where('is_available', true)->withReserved()
                ->orderByDesc('is_package')->orderBy('guest_capacity')->orderBy('name')->get(),
            'clientGuests' => $this->clientGuestCount($request),
            'canFavorite' => $this->isClient($request),
            'preferenceId' => $request->user()->favoriteSuppliers()->where('supplier_id', $supplier->id)->value('id'),
            'chosen' => $booking
                ? $booking->bookingProducts()->whereHas('product', fn ($q) => $q->where('supplier_id', $supplier->id))->pluck('quantity', 'supplier_product_id')
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isClient($request), 403);

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $preference = SupplierPreference::firstOrCreate(
            ['user_id' => $request->user()->id, 'supplier_id' => $data['supplier_id']],
            ['booking_id' => $this->activeBooking($request)?->id, 'notes' => $data['notes'] ?? null]
        );

        return back()->with('status', "♥ {$preference->supplier->name} is now one of your favorites. Your planner will see it.");
    }

    public function destroy(Request $request, SupplierPreference $preference): RedirectResponse
    {
        abort_unless($preference->user_id === $request->user()->id, 403);
        $preference->delete();

        return back()->with('status', "{$preference->supplier->name} removed from your favorites.");
    }

    /** The couple's expected guest count from their budget, used to show which packages fit. */
    private function clientGuestCount(Request $request): ?int
    {
        return $this->isClient($request) ? $request->user()->budget?->guest_count : null;
    }

    private function isClient(Request $request): bool
    {
        return $request->user()->role === 'client';
    }

    private function activeBooking(Request $request): ?Booking
    {
        return $this->isClient($request)
            ? $request->user()->clientBookings()->active()->orderBy('event_date')->first()
            : null;
    }
}
