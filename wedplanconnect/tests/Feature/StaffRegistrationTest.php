<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use App\Notifications\RegistrationAnswered;
use App\Notifications\StaffRegistered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signup(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Lorr Aljas', 'email' => 'lorr@example.com', 'phone' => '09171234567',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'privacy' => '1',
        ];
    }

    public function test_a_supplier_signs_up_and_waits_for_approval_without_couple_names(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->post('/register', $this->signup([
            'account_type' => 'vendor', 'business_name' => 'Lorr Aljas Event Styling', 'business_category' => 'Event Styling',
        ]))->assertRedirect('/login')->assertSessionHas('status', fn ($s) => str_contains($s, 'An administrator will review your account'));

        $this->assertGuest();
        $vendor = User::where('email', 'lorr@example.com')->sole();
        $this->assertSame('vendor', $vendor->role);
        $this->assertTrue($vendor->awaiting_approval);
        $this->assertSame(['name' => 'Lorr Aljas Event Styling', 'category' => 'Event Styling'], $vendor->pending_supplier);
        // The business isn't in the catalog until approved.
        $this->assertSame(0, Supplier::count());
        Notification::assertSentTo($admin, StaffRegistered::class);
    }

    public function test_a_supplier_needs_business_details_and_a_planner_does_not(): void
    {
        $this->post('/register', $this->signup(['account_type' => 'vendor']))
            ->assertSessionHasErrors(['business_name', 'business_category'])->assertSessionDoesntHaveErrors('bride_first_name');

        $this->post('/register', $this->signup(['account_type' => 'planner', 'bio' => 'Garden weddings in Cebu.']))->assertSessionHasNoErrors();
        $planner = User::where('email', 'lorr@example.com')->sole();
        $this->assertSame('planner', $planner->role);
        $this->assertTrue($planner->awaiting_approval);
        $this->assertSame('Garden weddings in Cebu.', $planner->bio);
    }

    public function test_couples_still_sign_up_and_start_planning_straight_away(): void
    {
        $this->post('/register', $this->signup([
            'bride_first_name' => 'Ana', 'bride_last_name' => 'Santos', 'groom_first_name' => 'Miguel', 'groom_last_name' => 'Reyes',
        ]))->assertRedirect('/my');

        $this->assertAuthenticated();
        $this->assertFalse(User::where('email', 'lorr@example.com')->sole()->awaiting_approval);
    }

    public function test_nobody_can_sign_up_as_an_admin(): void
    {
        $this->post('/register', $this->signup(['account_type' => 'admin']))->assertSessionHasErrors('account_type');
        $this->assertSame(0, User::count());
    }

    public function test_a_pending_account_cannot_log_in_and_is_hidden_from_couples(): void
    {
        $pending = User::factory()->planner()->awaitingApproval()->create(['name' => 'Pending Planner', 'email' => 'pending@example.com']);
        User::factory()->planner()->create(['name' => 'Approved Planner']);

        $this->post('/login', ['email' => 'pending@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => "Your Wedding Planner account is still waiting for the administrator's approval. We'll email you once it's approved."]);
        $this->assertGuest();

        $client = User::factory()->create();
        $this->actingAs($client)->get('/my/planner')->assertSee('Approved Planner')->assertDontSee('Pending Planner');
        $this->actingAs($client)->put('/my/planner', ['planner_id' => $pending->id])->assertSessionHasErrors('planner_id');
    }

    public function test_admin_approves_a_supplier_whose_business_then_joins_the_catalog(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $vendor = User::factory()->vendor()->awaitingApproval()->unverified()->create([
            'phone' => '09171234567', 'pending_supplier' => ['name' => 'Nanay Daisy Cakes', 'category' => 'Cakes'],
        ]);

        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('Waiting for your approval')->assertSee('Nanay Daisy Cakes');
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee("{$vendor->name} signed up as a Vendor/Supplier", false);

        $this->actingAs($admin)->patch("/admin/users/{$vendor->id}/approve")->assertSessionHas('status');

        $vendor->refresh();
        $this->assertFalse($vendor->awaiting_approval);
        $this->assertNull($vendor->pending_supplier);
        $this->assertTrue($vendor->hasVerifiedEmail());
        $this->assertSame('Nanay Daisy Cakes', $vendor->supplierProfile->name);
        $this->assertSame('Cakes', $vendor->supplierProfile->category);
        Notification::assertSentTo($vendor, RegistrationAnswered::class, fn ($n) => $n->approved);

        $this->post('/logout');
        $this->post('/login', ['email' => $vendor->email, 'password' => 'password'])->assertRedirect('/vendor');
    }

    public function test_admin_rejects_a_sign_up_which_is_removed_and_told_why(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $planner = User::factory()->planner()->awaitingApproval()->create(['email' => 'gone@example.com']);

        $this->actingAs($admin)->delete("/admin/users/{$planner->id}/reject", ['reason' => 'We are not hiring right now.'])->assertSessionHas('status');

        $this->assertSame(0, User::withTrashed()->where('email', 'gone@example.com')->count());
        Notification::assertSentTo($planner, RegistrationAnswered::class, fn ($n) => ! $n->approved && $n->reason === 'We are not hiring right now.');
    }

    public function test_only_pending_accounts_can_be_approved_and_only_by_the_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $active = User::factory()->planner()->create();
        $pending = User::factory()->planner()->awaitingApproval()->create();

        $this->actingAs($admin)->patch("/admin/users/{$active->id}/approve")->assertNotFound();
        $this->actingAs($admin)->delete("/admin/users/{$active->id}/reject")->assertNotFound();
        $this->actingAs($active)->patch("/admin/users/{$pending->id}/approve")->assertForbidden();
        $this->assertTrue($pending->fresh()->awaiting_approval);
    }
}
