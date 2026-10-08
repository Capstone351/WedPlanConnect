<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\QrStatusController;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $bookings = $request->user()->clientBookings()->orderByRaw("CASE WHEN status IN ('pending','confirmed') THEN 0 ELSE 1 END")->orderBy('event_date')->get();
        $booking = $bookings->first();

        if ($booking) {
            $booking->load([
                'tasks' => fn ($q) => $q->orderBy('due_date'),
                'bookingSuppliers.supplier:id,name,category',
                'preferences.supplier:id,name,category',
                'bookingProducts.product.supplier:id,name,category',
                'planner:id,name',
            ]);
        }

        return view('client.dashboard', [
            'booking' => $booking,
            'otherBookings' => $bookings->slice(1),
            'progress' => $booking?->progress() ?? 0,
            'paid' => $booking?->totalPaid() ?? 0,
        ]);
    }

    /** Logged-in clients see the same read-only status page without the QR/OTP step. */
    public function status(Request $request, Booking $booking): View
    {
        abort_unless($booking->client_id === $request->user()->id, 403);

        return view('qr.status', QrStatusController::statusData($booking) + ['viaQr' => false]);
    }
}
