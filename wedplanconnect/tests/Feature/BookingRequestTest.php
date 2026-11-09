<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $planner;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planner = User::factory()->planner()->create(['name' => 'Maria Clara Uy']);
        $this->client = User::factory()->create(['bride_name' => 'Ana Santos', 'groom_name' => 'Miguel Reyes', 'phone' => '09171234567']);
        $this->client->forceFill(['preferred_planner_id' => $this->planner->id, 'wedding_date' => today()->addMonths(5)])->save();
    }

    public function test_couple_sends_their_booking_to_their_chosen_planner(): void
    {
        $this->actingAs($this->client)->get('/my/wedding-date')->assertOk()->assertSee('Send booking to Maria Clara Uy');

        $this->actingAs($this->client)->post('/my/booking-request', ['package' => 'Garden Romance', 'venue' => 'Casa Gorordo Museum'])
            ->assertSessionHasNoErrors()->assertRedirect('/my/wedding-date');

        $booking = Booking::sole();
        $this->assertSame($this->planner->id, $booking->planner_id);
        $this->assertSame($this->client->id, $booking->client_id);
        $this->assertSame('Ana Santos & Miguel Reyes', $booking->client_name);
        $this->assertSame('09171234567', $booking->contact_number);
        $this->assertSame(today()->addMonths(5)->toDateString(), $booking->event_date->toDateString());
        $this->assertSame(['Casa Gorordo Museum', 'Garden Romance', 'pending', 0.0], [$booking->venue, $booking->package, $booking->status, (float) $booking->total_amount]);

        // The planner's Notifications tab shows it and opens the booking that was already made.
        $notification = $this->planner->notifications()->sole();
        $this->assertSame(BookingRequested::class, $notification->type);
        $this->actingAs($this->planner)->get('/notifications')->assertOk()->assertSee('New booking from Ana Santos &amp; Miguel Reyes', false);
        $this->actingAs($this->planner)->get("/notifications/{$notification->id}")->assertRedirect(route('bookings.show', $booking));

        $this->actingAs($this->planner)->get('/bookings')->assertOk()->assertSee('Ana Santos &amp; Miguel Reyes', false);
        $this->actingAs($this->planner)->get("/bookings/{$booking->id}")->assertOk()->assertSee('Set the price before confirming it.');
        $this->actingAs($this->client)->get('/my/wedding-date')->assertOk()->assertSee('Booking sent for');
        $this->actingAs($this->client)->get('/my')->assertOk()->assertSee('price to be set by your planner');

        // A second click doesn't create a duplicate.
        $this->actingAs($this->client)->post('/my/booking-request', ['package' => 'Garden Romance', 'venue' => 'Casa Gorordo Museum'])
            ->assertSessionHasErrors('booking');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_planner_must_set_the_price_before_confirming_a_sent_booking(): void
    {
        $booking = Booking::factory()->create(['planner_id' => $this->planner->id, 'client_id' => $this->client->id, 'total_amount' => 0]);

        $this->actingAs($this->planner)->patch("/bookings/{$booking->id}/confirm")->assertSessionHasErrors('status');
        $this->assertSame('pending', $booking->fresh()->status);

        $booking->update(['total_amount' => 120000]);
        $this->actingAs($this->planner)->patch("/bookings/{$booking->id}/confirm")->assertRedirect("/bookings/{$booking->id}/qr");
    }

    public function test_booking_needs_a_planner_a_date_and_a_free_slot(): void
    {
        $send = fn () => $this->actingAs($this->client)->post('/my/booking-request', ['package' => 'Garden Romance', 'venue' => 'Casa Gorordo Museum']);

        $this->actingAs($this->client)->post('/my/booking-request', ['package' => 'Not a package', 'venue' => ''])
            ->assertSessionHasErrors(['package', 'venue']);

        $this->client->forceFill(['preferred_planner_id' => null])->save();
        $send()->assertSessionHasErrors('booking');

        $this->client->forceFill(['preferred_planner_id' => $this->planner->id, 'wedding_date' => null])->save();
        $send()->assertSessionHasErrors('booking');

        // The planner took another wedding on the couple's date after they saved it.
        $this->client->forceFill(['wedding_date' => today()->addMonths(5)])->save();
        $other = Booking::factory()->create(['planner_id' => $this->planner->id, 'event_date' => today()->addMonths(5), 'venue' => 'Shangri-La Mactan']);
        $send()->assertSessionHasErrors('booking');

        // Another planner's couple already has the same date and venue.
        $other->update(['planner_id' => User::factory()->planner()->create()->id, 'venue' => 'Casa Gorordo Museum']);
        $send()->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('bookings', 1);
    }
}
