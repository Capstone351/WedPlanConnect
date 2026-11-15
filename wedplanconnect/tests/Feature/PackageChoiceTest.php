<?php

namespace Tests\Feature;

use App\Http\Controllers\Client\PackageController;
use App\Models\Booking;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageChoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create();
        $this->client->forceFill(['wedding_date' => today()->addMonths(6)])->save();
    }

    public function test_couple_chooses_any_package_after_setting_their_date(): void
    {
        $this->actingAs($this->client)->get('/my/package')->assertOk()
            ->assertSee('Package A')->assertSee('₱200,000', false)->assertSee('100 pax · Cake: 4 layers')->assertSee('Wine for Toasting')
            ->assertSee('Church Decoration')->assertSee('4 On-the-Day Coordinators')->assertSee('2 On-the-Day Coordinators')->assertSee('Bridesmaids Robe for Dress-up/Makeup')
            ->assertSee('Choose Package A')->assertSee('Choose Package B')->assertSee('Choose Package C')
            ->assertDontSee('budget of');

        $this->actingAs($this->client)->put('/my/package', ['package' => 'Package A'])->assertSessionHasNoErrors()->assertRedirect('/my/package')
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'book an in-person meeting with your planner for the contract signing and down payment'));
        $this->assertSame('Package A', $this->client->fresh()->wedding_package);

        $this->actingAs($this->client)->get('/my/package')->assertOk()->assertSee('Your chosen package')
            ->assertSee('Next steps for Package A')->assertSee('contract signing and down payment');

        // The meeting topic is filled in for them, and they can still change it.
        $this->client->forceFill(['preferred_planner_id' => User::factory()->planner()->create()->id])->save();
        $this->actingAs($this->client)->get('/my/meetings')->assertOk()
            ->assertSee('Contract signing and down payment for Package A.')->assertSee('Filled in for you.');
        $this->actingAs($this->client)->get('/my')->assertOk()->assertSee('Package A · ₱200,000', false)->assertDontSee('Set your budget');

        $this->actingAs($this->client)->put('/my/package', ['package' => 'Classic Elegance'])->assertSessionHasErrors('package');
    }

    public function test_package_comes_after_the_wedding_date(): void
    {
        $this->client->forceFill(['wedding_date' => null])->save();

        $this->actingAs($this->client)->get('/my/package')->assertOk()->assertSee('Set your wedding date first')->assertDontSee('Choose Package C');
        $this->actingAs($this->client)->put('/my/package', ['package' => 'Package C'])->assertRedirect('/my/wedding-date');
        $this->assertNull($this->client->fresh()->wedding_package);

        // Saving the date leads straight to choosing a package.
        $this->actingAs($this->client)->put('/my/wedding-date', ['wedding_date' => today()->addMonths(6)->toDateString()])
            ->assertRedirect('/my/package');
    }

    public function test_couple_whose_budget_is_not_in_the_packages_books_an_in_person_meeting(): void
    {
        $planner = User::factory()->planner()->create();
        $this->client->forceFill(['preferred_planner_id' => $planner->id])->save();

        $page = $this->actingAs($this->client)->get('/my/package')->assertOk()->assertSee('Is your budget not in these packages?');
        $meetingLink = route('client.meetings', ['agenda' => PackageController::MEETING_AGENDA, 'mode' => 'in_person']);
        $this->assertStringContainsString(e($meetingLink), $page->getContent());

        $this->actingAs($this->client)->get($meetingLink)->assertOk()->assertSee(PackageController::MEETING_AGENDA);

        $this->actingAs($this->client)->post('/my/meetings', ['date' => today()->addDays(3)->toDateString(), 'time' => '10:00', 'mode' => 'in_person', 'agenda' => PackageController::MEETING_AGENDA])
            ->assertSessionHasNoErrors();
        $this->assertSame('in_person', Meeting::sole()->mode);
    }

    public function test_package_locks_once_booked(): void
    {
        Booking::factory()->create(['client_id' => $this->client->id, 'package' => 'Package A']);

        $this->actingAs($this->client)->get('/my/package')->assertOk()->assertSee('In your booking')->assertDontSee('Is your budget not in these packages?');
        $this->actingAs($this->client)->put('/my/package', ['package' => 'Package C'])->assertSessionHasErrors('package');
        $this->assertNull($this->client->fresh()->wedding_package);
    }

    public function test_budget_is_no_longer_shown_to_couples(): void
    {
        $this->actingAs($this->client)->get('/my')->assertOk()->assertDontSee('My Budget')->assertSee('Wedding Package');
    }

    public function test_planner_booking_form_is_prefilled_with_the_couples_package(): void
    {
        $planner = User::factory()->planner()->create();
        $this->client->forceFill(['preferred_planner_id' => $planner->id, 'wedding_package' => 'Package C'])->save();

        $this->actingAs($planner)->get("/bookings/create?client_id={$this->client->id}")->assertOk()
            ->assertSee('value="Package C"', false)->assertSee('130000');
    }
}
