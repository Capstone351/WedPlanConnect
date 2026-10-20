<?php

namespace Tests\Feature;

use App\Mail\QrOtpMail;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QrStatusTest extends TestCase
{
    use RefreshDatabase;

    private function scanAndCaptureOtp(Booking $booking): string
    {
        $otp = null;
        Mail::fake();

        $this->get("/status/{$booking->qr_token}")->assertOk()->assertSee('Verify it');

        Mail::assertSent(QrOtpMail::class, function (QrOtpMail $mail) use ($booking, &$otp) {
            $otp = $mail->otp;

            return $mail->hasTo($booking->client->email);
        });

        return $otp;
    }

    public function test_scanning_sends_an_otp_and_does_not_reveal_status(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $this->scanAndCaptureOtp($booking->fresh());

        $this->get("/status/{$booking->fresh()->qr_token}/view")->assertRedirect();
    }

    public function test_correct_otp_grants_access_once(): void
    {
        $booking = Booking::factory()->confirmed()->create()->fresh();
        $otp = $this->scanAndCaptureOtp($booking);

        $this->post("/status/{$booking->qr_token}/verify", ['otp' => $otp])->assertRedirect("/status/{$booking->qr_token}/view");
        $this->get("/status/{$booking->qr_token}/view")->assertOk()->assertSee($booking->client_name);

        // Single use: the PIN is cleared after success.
        $this->assertNull($booking->fresh()->otp_code);
    }

    public function test_wrong_otp_is_rejected(): void
    {
        $booking = Booking::factory()->confirmed()->create()->fresh();
        $otp = $this->scanAndCaptureOtp($booking);
        $wrong = $otp === '111111' ? '222222' : '111111';

        $this->post("/status/{$booking->qr_token}/verify", ['otp' => $wrong])->assertSessionHasErrors('otp');
        $this->get("/status/{$booking->qr_token}/view")->assertRedirect("/status/{$booking->qr_token}");
    }

    public function test_expired_otp_is_rejected(): void
    {
        $booking = Booking::factory()->confirmed()->create()->fresh();
        $otp = $this->scanAndCaptureOtp($booking);

        $this->travel(11)->minutes();

        $this->post("/status/{$booking->qr_token}/verify", ['otp' => $otp])->assertSessionHasErrors('otp');
    }

    public function test_otp_is_stored_hashed(): void
    {
        $booking = Booking::factory()->confirmed()->create()->fresh();
        $otp = $this->scanAndCaptureOtp($booking);

        $this->assertNotSame($otp, $booking->fresh()->otp_code);
        $this->assertSame(hash('sha256', $otp), $booking->fresh()->otp_code);
    }

    public function test_unknown_or_cancelled_tokens_show_invalid_page(): void
    {
        $this->get('/status/'.str_repeat('a', 64))->assertOk()->assertSee('not available');

        $booking = Booking::factory()->confirmed()->create()->fresh();
        $token = $booking->qr_token;
        $booking->update(['status' => 'cancelled']);
        $booking->invalidateQrAccess();

        $this->get("/status/{$token}")->assertSee('not available');
    }

    public function test_qr_code_uses_the_public_url_when_one_is_set(): void
    {
        config(['wedplan.public_url' => 'https://wedplan-demo.ngrok-free.app/']);
        $booking = Booking::factory()->confirmed()->create();

        $this->assertSame("https://wedplan-demo.ngrok-free.app/status/{$booking->qr_token}", $booking->statusUrl());
    }

    public function test_pages_reached_through_the_local_tunnel_use_https_links(): void
    {
        $this->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'wedplan-demo.ngrok-free.app'])
            ->assertOk()->assertSee('https://wedplan-demo.ngrok-free.app/login', false);
    }
}
