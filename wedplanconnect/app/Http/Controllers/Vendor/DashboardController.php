<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSupplier;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Vendor/Supplier portal: read-only view of assigned bookings and own catalog profile.
 * Client contact details and payments are never exposed here.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $supplier = $this->supplier($request);
        $assignments = $supplier ? $this->assignments($supplier) : collect();

        $upcoming = $assignments->filter(fn ($a) => in_array($a->booking->status, Booking::ACTIVE_STATUSES) && $a->booking->event_date->gte(today()))
            ->sortBy(fn ($a) => $a->booking->event_date)->values();

        return view('vendor.dashboard', [
            'supplier' => $supplier,
            'next' => $upcoming->first(),
            'assignments' => $upcoming,
            'counts' => [
                'active' => $assignments->filter(fn ($a) => in_array($a->booking->status, Booking::ACTIVE_STATUSES))->count(),
                'upcoming' => $upcoming->count(),
                'thisWeek' => $upcoming->filter(fn ($a) => $a->booking->event_date->lte(today()->addDays(7)))->count(),
                'completed' => $assignments->filter(fn ($a) => $a->booking->status === 'completed')->count(),
            ],
        ]);
    }

    public function bookings(Request $request): View
    {
        $supplier = $this->supplier($request);

        return view('vendor.bookings', [
            'supplier' => $supplier,
            'assignments' => $supplier ? $this->assignments($supplier)->sortByDesc(fn ($a) => $a->booking->event_date)->values() : collect(),
        ]);
    }

    public function booking(Request $request, Booking $booking): View
    {
        $supplier = $this->supplier($request);
        abort_unless($supplier, 403);

        $assignment = BookingSupplier::where('booking_id', $booking->id)->where('supplier_id', $supplier->id)->first();
        abort_unless($assignment, 403, 'You are not assigned to this booking.');

        return view('vendor.booking', [
            'booking' => $booking->load('planner:id,name,email,phone'),
            'assignment' => $assignment,
            // Only this vendor's own products ordered for the wedding.
            'items' => $booking->bookingProducts()->whereHas('product', fn ($q) => $q->withTrashed()->where('supplier_id', $supplier->id))->with('product')->get(),
        ]);
    }

    public function profile(Request $request): View
    {
        return view('vendor.profile', ['supplier' => $this->supplier($request)]);
    }

    public function availability(Request $request): RedirectResponse
    {
        $supplier = $this->supplier($request);
        abort_unless($supplier, 403);

        $data = $request->validate(['availability' => ['required', Rule::in(['available', 'unavailable'])]]);
        $supplier->update($data);

        return back()->with('status', 'Your service availability is now '.ucfirst($data['availability']).'.');
    }

    private function supplier(Request $request): ?Supplier
    {
        return $request->user()->supplierProfile;
    }

    private function assignments(Supplier $supplier)
    {
        return BookingSupplier::where('supplier_id', $supplier->id)
            ->whereHas('booking')
            ->with('booking:id,client_name,event_date,venue,package,status,planner_id')
            ->get();
    }
}
