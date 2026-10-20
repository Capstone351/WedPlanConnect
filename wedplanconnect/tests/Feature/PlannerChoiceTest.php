<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerChoiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: User}
     */
    private function clientWithOneBookedPlanner(): array
    {
        $date = today()->addMonths(3);
        $client = User::factory()->create();
        $client->forceFill(['wedding_date' => $date])->save();

        $booked = User::factory()->planner()->create(['name' => 'Booked Planner']);
        $free = User::factory()->planner()->create(['name' => 'Free Planner']);
        Booking::factory()->create(['planner_id' => $booked->id, 'event_date' => $date, 'status' => 'confirmed']);

        return [$client, $booked, $free];
    }

    public function test_planner_booked_on_the_couples_date_is_shown_as_unavailable(): void
    {
        [$client, $booked] = $this->clientWithOneBookedPlanner();

        $page = $this->actingAs($client)->get('/my/planner')->assertOk()->assertSee('Booked on your date');

        $this->assertMatchesRegularExpression('/value="'.$booked->id.'"[^>]*disabled/', $page->getContent());
    }

    public function test_couple_cannot_choose_a_planner_booked_on_their_date(): void
    {
        [$client, $booked, $free] = $this->clientWithOneBookedPlanner();

        $this->actingAs($client)->put('/my/planner', ['planner_id' => $booked->id])->assertSessionHasErrors('planner_id');
        $this->assertNull($client->fresh()->preferred_planner_id);

        $this->actingAs($client)->put('/my/planner', ['planner_id' => $free->id])->assertSessionHasNoErrors();
        $this->assertSame($free->id, $client->fresh()->preferred_planner_id);
    }

    public function test_cancelled_bookings_do_not_block_a_planner(): void
    {
        [$client, $booked] = $this->clientWithOneBookedPlanner();
        Booking::where('planner_id', $booked->id)->update(['status' => 'cancelled']);

        $this->actingAs($client)->put('/my/planner', ['planner_id' => $booked->id])->assertSessionHasNoErrors();
    }

    public function test_couple_with_a_planner_cannot_pick_a_date_that_planner_is_booked(): void
    {
        $planner = User::factory()->planner()->create(['name' => 'Maria Clara Uy']);
        $busyDay = today()->addMonths(4);
        Booking::factory()->create(['planner_id' => $planner->id, 'event_date' => $busyDay, 'status' => 'pending']);
        $client = User::factory()->create();
        $client->forceFill(['preferred_planner_id' => $planner->id])->save();

        $this->actingAs($client)->get('/my/wedding-date')
            ->assertOk()->assertSee('Maria Clara Uy is already booked on:')->assertSee($busyDay->format('M j, Y'));
        $this->actingAs($client)->getJson('/my/wedding-date/availability?date='.$busyDay->toDateString())
            ->assertOk()->assertJson(['planner_busy' => true]);

        $this->actingAs($client)->put('/my/wedding-date', ['wedding_date' => $busyDay->toDateString()])
            ->assertSessionHasErrors('wedding_date');
        $this->actingAs($client)->put('/my/wedding-date', ['wedding_date' => $busyDay->copy()->addDay()->toDateString(), 'wedding_date_alt' => $busyDay->toDateString()])
            ->assertSessionHasErrors('wedding_date_alt');
        $this->assertNull($client->fresh()->wedding_date);

        $this->actingAs($client)->put('/my/wedding-date', ['wedding_date' => $busyDay->copy()->addDay()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame($planner->id, $client->fresh()->preferred_planner_id);
    }
}
