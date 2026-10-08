<?php

namespace App\Http\Controllers;

use App\Mail\QrOtpMail;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * UC-10 Client QR Code Status Scan with two-factor access (QR token + emailed OTP).
 *
 * 1. Scanning the QR opens /status/{token}; the token is validated and a 6-digit OTP is emailed.
 * 2. The client enters the OTP; on success it is invalidated (single use) and the session is marked verified.
 * 3. The read-only status page is rendered only for verified sessions.
 */
class QrStatusController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $booking = $this->resolve($token);

        if (! $booking) {
            return view('qr.invalid');
        }

        if ($this->isVerified($request, $booking)) {
            return redirect()->route('qr.status', $token);
        }

        $otpActive = $booking->otp_code && $booking->otp_expires_at?->isFuture();

        if (! $otpActive) {
            $this->dispatchOtp($booking);
        }

        return view('qr.verify', [
            'booking' => $booking,
            'token' => $token,
            'maskedEmail' => $this->maskEmail($booking->client->email),
        ]);
    }

    public function send(string $token): RedirectResponse
    {
        $booking = $this->resolve($token);
        abort_unless($booking, 404);

        $this->dispatchOtp($booking);

        return back()->with('status', 'A new One-Time PIN has been sent to your registered email.');
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        $booking = $this->resolve($token);
        abort_unless($booking, 404);

        $request->validate(['otp' => ['required', 'digits:6']], ['otp.digits' => 'Enter the 6-digit PIN from your email.']);

        if (! $booking->otp_code || ! $booking->otp_expires_at?->isFuture()) {
            return back()->withErrors(['otp' => 'This PIN has expired. Request a new one below.']);
        }

        if ($booking->otp_attempts >= config('wedplan.otp_max_attempts')) {
            $booking->forceFill(['otp_code' => null, 'otp_expires_at' => null])->save();

            return back()->withErrors(['otp' => 'Too many incorrect attempts. Please request a new PIN.']);
        }

        if (! hash_equals($booking->otp_code, hash('sha256', $request->input('otp')))) {
            $booking->increment('otp_attempts');

            return back()->withErrors(['otp' => 'Incorrect PIN. Please try again.']);
        }

        // Single use: invalidate immediately after a successful verification.
        $booking->forceFill(['otp_code' => null, 'otp_expires_at' => null, 'otp_attempts' => 0])->save();

        $request->session()->regenerate();
        $request->session()->put("qr_verified.{$booking->id}", now()->timestamp);

        return redirect()->route('qr.status', $token);
    }

    public function status(Request $request, string $token): View|RedirectResponse
    {
        $booking = $this->resolve($token);

        if (! $booking) {
            return view('qr.invalid');
        }

        if (! $this->isVerified($request, $booking)) {
            return redirect()->route('qr.show', $token);
        }

        return view('qr.status', $this->statusData($booking) + ['viaQr' => true]);
    }

    /** Shared with the logged-in client status view. */
    public static function statusData(Booking $booking): array
    {
        $booking->load([
            'bookingSuppliers' => fn ($q) => $q->with('supplier:id,name,category'),
            'tasks' => fn ($q) => $q->orderBy('due_date'),
            'bookingProducts.product.supplier:id,name,category',
            'planner:id,name',
        ]);

        return [
            'booking' => $booking,
            'progress' => $booking->progress(),
            'paid' => $booking->totalPaid(),
        ];
    }

    /** Only confirmed or completed bookings with a live token are reachable. */
    private function resolve(string $token): ?Booking
    {
        return Booking::where('qr_token', $token)->whereIn('status', ['confirmed', 'completed'])->with('client')->first();
    }

    private function isVerified(Request $request, Booking $booking): bool
    {
        $at = $request->session()->get("qr_verified.{$booking->id}");

        return $at && now()->timestamp - $at < config('wedplan.qr_session_minutes') * 60;
    }

    private function dispatchOtp(Booking $booking): void
    {
        $otp = (string) random_int(100000, 999999);

        $booking->forceFill([
            'otp_code' => hash('sha256', $otp),
            'otp_expires_at' => now()->addMinutes(config('wedplan.otp_ttl_minutes')),
            'otp_attempts' => 0,
        ])->save();

        Mail::to($booking->client->email)->send(new QrOtpMail($booking, $otp));
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email) + [1 => ''];

        return Str::substr($local, 0, 2).str_repeat('•', max(2, Str::length($local) - 2)).'@'.$domain;
    }
}
