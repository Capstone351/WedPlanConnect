<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\BookingRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
        $this->client->forceFill(['preferred_planner_id' => $this->planner->id, 'wedding_date' => today()->addMonths(5), 'wedding_package' => 'Package B'])->save();
    }

    private function send(): TestResponse
    {
        return $this->actingAs($this->client)->post('/my/booking-request', ['venue' => 'Casa Gorordo Museum']);
    }

    public function test_couple_sends_their_booking_to_their_chosen_planner(): void
    {
        $this->actingAs($this->client)->get('/my/wedding-date')->assertOk()->assertSee('Send booking to Maria Clara Uy')->assertSee('Package B');

        // Even with a package chosen, the couple is sent to book a meeting: the down payment is made there.
        $agenda = 'Go over Package B and pay the down payment.';
        $this->send()->assertSessionHasNoErrors()
            ->assertRedirect(route('client.meetings', ['mode' => 'in_person', 'agenda' => $agenda]))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Next, book an in-person meeting with them to pay your down payment.'));
        $this->actingAs($this->client)->get(route('client.meetings', ['agenda' => $agenda]))->assertOk()
            ->assertSee('Your down payment is made at this meeting.')->assertSee($agenda);
        $this->actingAs($this->client)->get('/my/wedding-date')->assertOk()->assertSee('Book a meeting');

        Meeting::factory()->create(['client_id' => $this->client->id, 'planner_id' => $this->planner->id, 'scheduled_at' => now()->addWeek(), 'status' => 'requested']);
        $this->actingAs($this->client)->get('/my/wedding-date')->assertOk()->assertSee('Bring your down payment.')->assertDontSee('Book a meeting');

        $booking = Booking::sole();
        $this->assertSame($this->planner->id, $booking->planner_id);
        $this->assertSame($this->client->id, $booking->client_id);
        $this->assertSame('Ana Santos & Miguel Reyes', $booking->client_name);
        $this->assertSame('09171234567', $booking->contact_number);
        $this->assertSame(today()->addMonths(5)->toDateString(), $booking->event_date->toDateString());
        $this->assertSame(['Casa Gorordo Museum', 'Package B', 'pending', 160000.0], [$booking->venue, $booking->package, $booking->status, (float) $booking->total_amount]);

        // The planner's Notifications tab shows it and opens the booking that was already made.
        $notification = $this->planner->notifications()->sole();
        $this->assertSame(BookingRequested::class, $notification->type);
        $this->actingAs($this->planner)->get('/notifications')->assertOk()->assertSee('New booking from Ana Santos &amp; Miguel Reyes', false)->assertSee('₱160,000', false);
        $this->actingAs($this->planner)->get("/notifications/{$notification->id}")->assertRedirect(route('bookings.show', $booking));

        $this->actingAs($this->planner)->get('/bookings')->assertOk()->assertSee('Ana Santos &amp; Miguel Reyes', false);
        $this->actingAs($this->planner)->get("/bookings/{$booking->id}")->assertOk()->assertDontSee('Still to agree with the couple');
        $this->actingAs($this->planner)->patch("/bookings/{$booking->id}/confirm")->assertRedirect("/bookings/{$booking->id}/qr");
        $this->actingAs($this->client)->get('/my/wedding-date')->assertOk()->assertSee('Your wedding is booked for');

        // A second click doesn't create a duplicate.
        $this->send()->assertSessionHasErrors('booking');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_planner_must_set_the_price_before_confirming_a_booking_without_one(): void
    {
        $booking = Booking::factory()->create(['planner_id' => $this->planner->id, 'client_id' => $this->client->id, 'total_amount' => 0]);

        $this->actingAs($this->planner)->patch("/bookings/{$booking->id}/confirm")->assertSessionHasErrors('status');
        $this->assertSame('pending', $booking->fresh()->status);

        $booking->update(['total_amount' => 120000]);
        $this->actingAs($this->planner)->patch("/bookings/{$booking->id}/confirm")->assertRedirect("/bookings/{$booking->id}/qr");
    }

    public function test_couple_can_send_their_booking_before_choosing_a_package_or_venue(): void
    {
        $this->client->forceFill(['wedding_package' => null, 'wedding_venue' => 'Old venue'])->save();
        $this->actingAs($this->client)->get('/my/wedding-date')->assertOk()->assertSee('To be discussed with your planner');

        $this->actingAs($this->client)->post('/my/booking-request', ['venue' => ''])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'book an in-person meeting with them to agree on the venue, package and price and pay your down payment.'));

        $booking = Booking::sole();
        $this->assertSame([null, null, 0.0], [$booking->venue, $booking->package, (float) $booking->total_amount]);
        $this->assertSame('Old venue', $this->client->fresh()->wedding_venue);
        $this->assertStringContainsString('Venue to be discussed · Package to be discussed · price to be discussed', $this->planner->notifications()->sole()->data['body']);

        $this->actingAs($this->client)->get('/my/wedding-date')->assertOk()->assertSee('Meet')->assertSee('Book a meeting');
        $this->actingAs($this->planner)->get('/bookings')->assertOk()->assertSee('Venue to be discussed');
        $this->actingAs($this->planner)->get("/bookings/{$booking->id}")->assertOk()->assertSee('venue, package and price');

        // The planner fills in what was agreed at the meeting, then confirms.
        $this->actingAs($this->planner)->patch("/bookings/{$booking->id}/confirm")->assertSessionHasErrors('status');
        $this->actingAs($this->planner)->put("/bookings/{$booking->id}", [
            'client_id' => $this->client->id, 'client_name' => $booking->client_name, 'contact_number' => '09171234567',
            'event_date' => $booking->event_date->toDateString(), 'venue' => 'Casa Gorordo Museum', 'package' => 'Custom Package', 'total_amount' => 110000,
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->planner)->patch("/bookings/{$booking->id}/confirm")->assertRedirect("/bookings/{$booking->id}/qr");
    }

    public function test_bookings_without_a_venue_do_not_clash_with_each_other(): void
    {
        $other = Booking::factory()->create(['event_date' => today()->addMonths(5), 'venue' => null, 'package' => null]);

        $this->actingAs($this->client)->post('/my/booking-request', ['venue' => ''])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bookings', 2);
        $this->assertNull($other->fresh()->venue);
    }

    public function test_booking_needs_a_planner_a_date_and_a_free_slot(): void
    {
        $this->client->forceFill(['preferred_planner_id' => null])->save();
        $this->send()->assertSessionHasErrors('booking');

        $this->client->forceFill(['preferred_planner_id' => $this->planner->id, 'wedding_date' => null])->save();
        $this->send()->assertSessionHasErrors('booking');

        // The planner took another wedding on the couple's date after they saved it.
        $this->client->forceFill(['wedding_date' => today()->addMonths(5)])->save();
        $other = Booking::factory()->create(['planner_id' => $this->planner->id, 'event_date' => today()->addMonths(5), 'venue' => 'Shangri-La Mactan']);
        $this->send()->assertSessionHasErrors('booking');

        // Another planner's couple already has the same date and venue.
        $other->update(['planner_id' => User::factory()->planner()->create()->id, 'venue' => 'Casa Gorordo Museum']);
        $this->send()->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('bookings', 1);
    }
}
