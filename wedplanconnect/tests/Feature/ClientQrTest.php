<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_couple_is_notified_and_can_view_their_qr_code_once_confirmed(): void
    {
        $client = User::factory()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id]);

        // Not confirmed yet: no QR button, and the page sends them back.
        $this->actingAs($client)->get('/my')->assertOk()->assertDontSee('My QR code');
        $this->actingAs($client)->get("/my/qr/{$booking->id}")->assertRedirect('/my')->assertSessionHasErrors('qr');

        $this->actingAs($booking->planner)->patch("/bookings/{$booking->id}/confirm")->assertRedirect("/bookings/{$booking->id}/qr");

        $notification = $client->notifications()->sole();
        $this->assertSame(BookingConfirmed::class, $notification->type);
        $this->actingAs($client)->get("/notifications/{$notification->id}")->assertRedirect(route('client.qr', $booking));

        $this->actingAs($client)->get('/my')->assertOk()->assertSee('My QR code');
        $this->actingAs($client)->get("/my/qr/{$booking->id}")->assertOk()
            ->assertSee('<svg', false)->assertSee('Download image')->assertSee('data-qr-png', false)->assertSee($booking->client_name);
    }

    public function test_couple_cannot_view_another_couples_qr_code(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        $this->actingAs(User::factory()->create())->get("/my/qr/{$booking->id}")->assertForbidden();
    }

    public function test_cancelled_booking_has_no_qr_code(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $this->actingAs($booking->planner)->patch("/bookings/{$booking->id}/cancel", ['reason' => 'Couple cancelled.']);

        $this->actingAs($booking->client)->get("/my/qr/{$booking->id}")->assertRedirect('/my');
    }

    public function test_planner_qr_page_still_shows_the_card(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        $this->actingAs($booking->planner)->get("/bookings/{$booking->id}/qr")->assertOk()
            ->assertSee('Scan to view your wedding preparations')->assertSee('<svg', false);
    }
}
