<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\QrStatusController;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

    /** The couple's own QR code, available once their planner confirms the booking. */
    public function qr(Request $request, Booking $booking): View|RedirectResponse
    {
        abort_unless($booking->client_id === $request->user()->id, 403);

        if (! $booking->qr_token || ! in_array($booking->status, ['confirmed', 'completed'], true)) {
            return redirect()->route('client.dashboard')->withErrors(['qr' => 'Your QR code will be ready once your planner confirms your booking.']);
        }

        return view('client.qr', [
            'booking' => $booking->loadMissing('client'),
            'qrSvg' => QrCode::format('svg')->size(280)->margin(1)->errorCorrection('M')->generate($booking->statusUrl()),
        ]);
    }
}
