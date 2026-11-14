<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaceSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_venue_fields_suggest_cebu_reception_venues_but_not_churches(): void
    {
        $planner = User::factory()->planner()->create();
        $client = User::factory()->create();
        $client->forceFill(['preferred_planner_id' => $planner->id, 'wedding_date' => today()->addMonths(6)])->save();

        $this->actingAs($client)->get('/my/wedding-date')->assertOk()
            ->assertSee('Preferred reception venue')
            ->assertSee('list="cebu-venues"', false)->assertSee('<datalist id="cebu-venues">', false)
            ->assertSee('Shangri-La Mactan, Lapu-Lapu City')->assertSee('label="Hotel"', false)
            ->assertDontSee('label="Church"', false)->assertDontSee('Basilica Minore del Santo Niño');

        $this->actingAs($planner)->get('/bookings/create')->assertOk()
            ->assertSee('Reception venue')
            ->assertSee('list="cebu-venues"', false)->assertSee('Radisson Blu Hotel Cebu, Cebu City')
            ->assertDontSee('Cebu Metropolitan Cathedral');
    }

    public function test_meeting_place_suggests_cebu_meetup_spots(): void
    {
        $booking = Booking::factory()->create();

        $this->actingAs($booking->client)->get('/my/meetings')->assertOk()
            ->assertSee('list="cebu-meetup"', false)->assertSee('Ayala Center Cebu, Cebu Business Park, Cebu City');
    }

    public function test_a_place_that_is_not_listed_is_still_accepted(): void
    {
        $planner = User::factory()->planner()->create();
        $client = User::factory()->create(['phone' => '09171234567']);
        $client->forceFill(['preferred_planner_id' => $planner->id, 'wedding_date' => today()->addMonths(6)])->save();

        $this->actingAs($client)->post('/my/booking-request', ['venue' => "Lola's Garden, Talisay City"])->assertSessionHasNoErrors();

        $this->assertSame("Lola's Garden, Talisay City", Booking::sole()->venue);
    }
}
