<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCreatesAccountsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function account(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Ana Reyes', 'email' => 'ana@example.com', 'role' => 'planner',
            'password' => 'Secret123', 'password_confirmation' => 'Secret123',
        ];
    }

    public function test_the_form_opens_with_the_chosen_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/users/create?role=planner')->assertOk()
            ->assertSee('Add Wedding Planner')->assertSee('<option value="planner" selected', false);
        $this->actingAs($admin)->get('/admin/users/create?role=vendor')->assertOk()
            ->assertSee('Add Supplier Account')->assertSee('<option value="vendor" selected', false)->assertSee('Create a new supplier business');
    }

    public function test_admin_creates_a_planner_who_can_log_in(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/users', $this->account(['bio' => 'Garden weddings in Cebu.']))->assertRedirect('/admin/users');

        $planner = User::where('email', 'ana@example.com')->sole();
        $this->assertSame('planner', $planner->role);
        $this->assertTrue($planner->hasVerifiedEmail());

        $this->post('/logout');
        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'Secret123'])->assertRedirect();
        $this->assertAuthenticatedAs($planner);
    }

    public function test_admin_creates_a_supplier_account_with_a_new_supplier_business(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post('/admin/users', $this->account([
            'name' => 'Lorr Aljas', 'email' => 'lorr@example.com', 'role' => 'vendor', 'supplier_mode' => 'new',
            'new_supplier_name' => 'Lorr Aljas Event Styling', 'new_supplier_category' => 'Event Styling', 'new_supplier_phone' => '+639171234567',
        ]))->assertSessionHasNoErrors();

        $vendor = User::where('email', 'lorr@example.com')->sole();
        $supplier = $vendor->supplierProfile;
        $this->assertSame('Lorr Aljas Event Styling', $supplier->name);
        $this->assertSame('Event Styling', $supplier->category);
        $this->assertSame('lorr@example.com', $supplier->email);

        // The new supplier can manage their products straight away.
        $this->actingAs($vendor)->get('/vendor/products')->assertOk();
    }

    public function test_admin_links_a_supplier_account_to_an_existing_supplier_business(): void
    {
        $supplier = Supplier::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->post('/admin/users', $this->account([
            'role' => 'vendor', 'supplier_mode' => 'existing', 'supplier_id' => $supplier->id,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(User::where('email', 'ana@example.com')->value('id'), $supplier->fresh()->user_id);
        $this->assertSame(1, Supplier::count());
    }

    public function test_a_new_supplier_business_needs_its_details_but_planners_ignore_them(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/users', $this->account(['role' => 'vendor', 'supplier_mode' => 'new']))
            ->assertSessionHasErrors(['new_supplier_name', 'new_supplier_category', 'new_supplier_phone']);
        $this->assertSame(0, User::where('email', 'ana@example.com')->count());

        $this->actingAs($admin)->post('/admin/users', $this->account(['supplier_mode' => 'new']))->assertSessionHasNoErrors();
        $this->assertSame(0, Supplier::count());
    }
}
