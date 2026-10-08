<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_are_redirected_to_their_role_dashboard(): void
    {
        foreach (['admin' => '/admin', 'planner' => '/planner', 'client' => '/my', 'vendor' => '/vendor'] as $role => $path) {
            $user = User::factory()->role($role)->create();

            $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect($path);
            $this->post('/logout');
        }
    }

    public function test_account_locks_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->assertTrue($user->fresh()->isLocked());

        // Correct password is still rejected while locked.
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'nope']);
        $this->assertSame(1, $user->fresh()->failed_attempts);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertSame(0, $user->fresh()->failed_attempts);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_deactivated_accounts_cannot_log_in(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_rbac_blocks_other_roles_with_403(): void
    {
        $client = User::factory()->create();

        $this->actingAs($client)->get('/admin')->assertForbidden();
        $this->actingAs($client)->get('/bookings')->assertForbidden();
        $this->actingAs($client)->get('/vendor')->assertForbidden();
        $this->actingAs(User::factory()->planner()->create())->get('/admin/users')->assertForbidden();
    }

    public function test_staff_can_preview_the_catalog_but_not_save_favorites(): void
    {
        $admin = User::factory()->admin()->create();
        $supplier = \App\Models\Supplier::factory()->create();

        $this->actingAs($admin)->get('/my/catalog')->assertOk()->assertSee('Staff preview');
        $this->actingAs($admin)->get("/my/catalog/{$supplier->id}")->assertOk();
        $this->actingAs($admin)->post('/my/catalog/preferences', ['supplier_id' => $supplier->id])->assertForbidden();
    }

    public function test_forbidden_page_shows_current_account_and_switch_option(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Fe Admin']);

        $this->actingAs($admin)->get('/vendor')->assertForbidden()
            ->assertSee('Fe Admin')->assertSee('Switch account')->assertSee('Go to my dashboard');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/bookings')->assertRedirect('/login');
    }

    public function test_clients_can_self_register(): void
    {
        $this->post('/register', [
            'name' => 'Ana & Miguel', 'email' => 'ana@example.com',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'privacy' => '1',
        ])->assertRedirect('/my');

        $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'role' => 'client']);
    }
}
