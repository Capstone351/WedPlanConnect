<?php

namespace Tests\Feature;

use App\Models\ClientBudget;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_adds_a_package_with_guest_count_and_inclusions(): void
    {
        $vendor = User::factory()->vendor()->create();
        $supplier = Supplier::factory()->create(['user_id' => $vendor->id]);

        $this->actingAs($vendor)->post('/vendor/products', [
            'name' => 'Garden package', 'is_package' => '1', 'guest_capacity' => 100,
            'inclusions' => "Bridal bouquet\nArch florals", 'price' => 85000, 'unit' => 'per package', 'is_available' => '1',
        ])->assertRedirect('/vendor/products');

        $package = $supplier->products()->first();
        $this->assertTrue($package->is_package);
        $this->assertSame(100, $package->guest_capacity);
        $this->assertSame(['Bridal bouquet', 'Arch florals'], $package->inclusionList());
    }

    public function test_a_package_needs_a_guest_count_and_single_products_drop_package_fields(): void
    {
        $vendor = User::factory()->vendor()->create();
        $supplier = Supplier::factory()->create(['user_id' => $vendor->id]);

        $this->actingAs($vendor)->post('/vendor/products', ['name' => 'Garden package', 'is_package' => '1', 'is_available' => '1'])
            ->assertSessionHasErrors('guest_capacity');

        $this->actingAs($vendor)->post('/vendor/products', [
            'name' => 'Bouquet', 'is_package' => '0', 'guest_capacity' => 50, 'inclusions' => 'Roses', 'is_available' => '1',
        ])->assertSessionHasNoErrors();

        $single = $supplier->products()->first();
        $this->assertFalse($single->is_package);
        $this->assertNull($single->guest_capacity);
        $this->assertNull($single->inclusions);
    }

    public function test_client_finds_suppliers_with_packages_for_their_guest_count(): void
    {
        $client = User::factory()->create();
        ClientBudget::create(['user_id' => $client->id, 'total_amount' => 300000, 'guest_count' => 150]);

        $big = Supplier::factory()->create(['name' => 'Big Styling Co']);
        $small = Supplier::factory()->create(['name' => 'Small Styling Co']);
        SupplierProduct::create(['supplier_id' => $big->id, 'name' => 'Grand package', 'is_package' => true, 'guest_capacity' => 200, 'inclusions' => "Stage backdrop\nCenterpieces", 'is_available' => true]);
        SupplierProduct::create(['supplier_id' => $small->id, 'name' => 'Intimate package', 'is_package' => true, 'guest_capacity' => 50, 'is_available' => true]);

        $this->actingAs($client)->get('/my/catalog?guests=100')
            ->assertOk()->assertSee('Big Styling Co')->assertDontSee('Small Styling Co');

        $this->actingAs($client)->get("/my/catalog/{$big->id}")
            ->assertOk()->assertSee('Package · Good for 200 guests')->assertSee('Fits your 150 guests')->assertSee('Stage backdrop');

        $this->actingAs($client)->get("/my/catalog/{$small->id}")
            ->assertOk()->assertSee('Smaller than your 150 guests');
    }

    public function test_planner_sees_packages_in_the_catalog_preview(): void
    {
        $supplier = Supplier::factory()->create(['name' => 'Big Styling Co']);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'name' => 'Grand package', 'is_package' => true, 'guest_capacity' => 200, 'is_available' => true]);
        $planner = User::factory()->planner()->create();

        $this->actingAs($planner)->get('/my/catalog')->assertOk()->assertSee('1 package · up to 200 guests');
        $this->actingAs($planner)->get("/my/catalog/{$supplier->id}")->assertOk()->assertSee('Package · Good for 200 guests');
    }
}
