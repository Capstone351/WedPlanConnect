<?php

namespace Tests\Feature;

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

    public function test_catalog_shows_suppliers_without_their_offerings_or_guest_filter(): void
    {
        $client = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'Big Styling Co', 'description' => 'Elegant ballroom styling.']);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'name' => 'Grand package', 'is_package' => true, 'guest_capacity' => 200, 'inclusions' => "Stage backdrop\nCenterpieces", 'is_available' => true]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'name' => 'Bridal bouquet', 'price' => 6500, 'is_available' => true]);

        foreach ([$client, User::factory()->planner()->create()] as $viewer) {
            $this->actingAs($viewer)->get('/my/catalog')->assertOk()
                ->assertSee('Big Styling Co')->assertSee('View profile')
                ->assertDontSee('Packages good for at least')->assertDontSee('offering')->assertDontSee('up to 200 guests');

            $this->actingAs($viewer)->get("/my/catalog/{$supplier->id}")->assertOk()
                ->assertSee('Elegant ballroom styling.')
                ->assertDontSee('Grand package')->assertDontSee('Stage backdrop')->assertDontSee('Bridal bouquet')->assertDontSee('Good for 200 guests');
        }

        // The old guest filter is simply ignored.
        $this->actingAs($client)->get('/my/catalog?guests=500')->assertOk()->assertSee('Big Styling Co');
    }
}
