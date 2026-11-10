<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ClientBudget;
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
    }

    private function budget(int $amount): void
    {
        ClientBudget::create(['user_id' => $this->client->id, 'total_amount' => $amount]);
    }

    public function test_couple_chooses_a_package_within_their_budget(): void
    {
        $this->budget(170000);

        $this->actingAs($this->client)->get('/my/package')->assertOk()
            ->assertSee('Package A')->assertSee('₱200,000', false)->assertSee('100 pax · Cake: 4 layers')->assertSee('Wine for Toasting')
            ->assertSee('₱30,000 above your budget', false)->assertSee('Choose Package B')->assertSee('Choose Package C');

        $this->actingAs($this->client)->put('/my/package', ['package' => 'Package B'])->assertSessionHasNoErrors()->assertRedirect('/my/package');
        $this->assertSame('Package B', $this->client->fresh()->wedding_package);

        $this->actingAs($this->client)->get('/my/package')->assertOk()->assertSee('Your chosen package');
        $this->actingAs($this->client)->get('/my')->assertOk()->assertSee('Package B · ₱160,000', false);
    }

    public function test_package_above_the_budget_cannot_be_chosen(): void
    {
        $this->budget(170000);

        $this->actingAs($this->client)->put('/my/package', ['package' => 'Package A'])->assertSessionHasErrors('package');
        $this->actingAs($this->client)->put('/my/package', ['package' => 'Classic Elegance'])->assertSessionHasErrors('package');

        $this->assertNull($this->client->fresh()->wedding_package);
    }

    public function test_couple_below_every_package_is_asked_to_book_a_meeting(): void
    {
        $this->budget(100000);
        $this->client->forceFill(['preferred_planner_id' => User::factory()->planner()->create()->id])->save();

        $page = $this->actingAs($this->client)->get('/my/package')->assertOk()
            ->assertSee('is below our packages')->assertSee('Book a meeting')->assertDontSee('Choose Package C');

        $meetingLink = route('client.meetings', ['agenda' => "Our budget is ₱100,000. We'd like to talk about a package that fits it."]);
        $this->assertStringContainsString(e($meetingLink), $page->getContent());

        $this->actingAs($this->client)->get($meetingLink)->assertOk()->assertSee('Our budget is ₱100,000.', false);
    }

    public function test_package_needs_a_budget_and_locks_once_booked(): void
    {
        $this->actingAs($this->client)->get('/my/package')->assertOk()->assertSee('Set your budget first');
        $this->actingAs($this->client)->put('/my/package', ['package' => 'Package C'])->assertRedirect('/my/budget');

        $this->budget(300000);
        Booking::factory()->create(['client_id' => $this->client->id, 'package' => 'Package A']);

        $this->actingAs($this->client)->get('/my/package')->assertOk()->assertSee('In your booking');
        $this->actingAs($this->client)->put('/my/package', ['package' => 'Package C'])->assertSessionHasErrors('package');
        $this->assertNull($this->client->fresh()->wedding_package);
    }

    public function test_planner_booking_form_is_prefilled_with_the_couples_package(): void
    {
        $planner = User::factory()->planner()->create();
        $this->client->forceFill(['preferred_planner_id' => $planner->id, 'wedding_package' => 'Package C'])->save();

        $this->actingAs($planner)->get("/bookings/create?client_id={$this->client->id}")->assertOk()
            ->assertSee('value="Package C"', false)->assertSee('130000');
    }
}
