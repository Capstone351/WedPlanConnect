<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierProductTest extends TestCase
{
    use RefreshDatabase;

    private function vendorWithSupplier(): array
    {
        $vendor = User::factory()->vendor()->create();
        $supplier = Supplier::factory()->create(['user_id' => $vendor->id]);

        return [$vendor, $supplier];
    }

    public function test_vendor_adds_a_product_with_a_private_photo(): void
    {
        Storage::fake('local');
        [$vendor, $supplier] = $this->vendorWithSupplier();

        $this->actingAs($vendor)->post('/vendor/products', [
            'name' => 'Bridal bouquet', 'description' => 'Roses', 'price' => 6500, 'unit' => 'per piece',
            'is_available' => '1', 'image' => UploadedFile::fake()->image('bouquet.png', 800, 600),
        ])->assertRedirect('/vendor/products');

        $product = $supplier->products()->first();
        $this->assertSame('Bridal bouquet', $product->name);
        $this->assertTrue($product->is_available);
        Storage::disk('local')->assertExists($product->image_path);

        // Photo is served only to signed-in users.
        $this->actingAs($vendor)->get("/products/{$product->id}/image")->assertOk();
        $this->post('/logout');
        $this->get("/products/{$product->id}/image")->assertRedirect('/login');
    }

    public function test_vendor_cannot_edit_another_vendors_product(): void
    {
        [$vendor] = $this->vendorWithSupplier();
        $other = SupplierProduct::create(['supplier_id' => Supplier::factory()->create()->id, 'name' => 'Cake']);

        $this->actingAs($vendor)->get("/vendor/products/{$other->id}/edit")->assertNotFound();
        $this->actingAs($vendor)->delete("/vendor/products/{$other->id}")->assertNotFound();
        $this->assertNotSoftDeleted($other);
    }

    public function test_clients_and_planners_cannot_use_vendor_product_pages(): void
    {
        $this->actingAs(User::factory()->create())->get('/vendor/products')->assertForbidden();
        $this->actingAs(User::factory()->planner()->create())->get('/vendor/products')->assertForbidden();
    }

    public function test_planner_manages_products_for_suppliers_without_a_login(): void
    {
        $planner = User::factory()->planner()->create();
        $supplier = Supplier::factory()->create();

        $this->actingAs($planner)->post("/suppliers/{$supplier->id}/products", ['name' => 'LED wall', 'price' => 30000, 'is_available' => '1'])
            ->assertRedirect("/suppliers/{$supplier->id}/products");

        $this->assertSame(1, $supplier->products()->count());
    }

    public function test_planner_adds_product_to_booking_which_assigns_the_vendor(): void
    {
        $booking = Booking::factory()->create();
        $product = SupplierProduct::create(['supplier_id' => Supplier::factory()->create()->id, 'name' => 'Arch', 'price' => 28000, 'is_available' => true]);

        $this->actingAs($booking->planner)->post("/bookings/{$booking->id}/items", ['supplier_product_id' => $product->id, 'quantity' => 2])
            ->assertSessionHasNoErrors();

        $item = $booking->bookingProducts()->first();
        $this->assertSame(2, $item->quantity);
        $this->assertSame(56000.0, $item->subtotal());
        $this->assertTrue($booking->bookingSuppliers()->where('supplier_id', $product->supplier_id)->exists());
    }

    public function test_hidden_products_cannot_be_selected(): void
    {
        $booking = Booking::factory()->create();
        $product = SupplierProduct::create(['supplier_id' => Supplier::factory()->create()->id, 'name' => 'Old item', 'is_available' => false]);

        $this->actingAs($booking->planner)->post("/bookings/{$booking->id}/items", ['supplier_product_id' => $product->id, 'quantity' => 1])
            ->assertNotFound();
    }

    public function test_client_sees_offerings_and_set_up_but_not_supplier_contacts(): void
    {
        $client = User::factory()->create();
        $booking = Booking::factory()->create(['client_id' => $client->id]);
        $supplier = Supplier::factory()->create(['phone' => '+639209998877']);
        $product = SupplierProduct::create(['supplier_id' => $supplier->id, 'name' => 'Grazing table', 'price' => 22000, 'is_available' => true]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'name' => 'Hidden thing', 'is_available' => false]);
        $booking->bookingProducts()->create(['supplier_product_id' => $product->id, 'quantity' => 1, 'unit_price' => 22000]);

        $this->actingAs($client)->get("/my/catalog/{$supplier->id}")
            ->assertOk()->assertSee('Grazing table')->assertSee('In your set-up')
            ->assertDontSee('Hidden thing')->assertDontSee('+639209998877');

        $this->actingAs($client)->get('/my')->assertSee('Your wedding set-up')->assertSee('Grazing table');
    }

    public function test_vendor_sees_only_their_own_items_on_an_assigned_booking(): void
    {
        [$vendor, $supplier] = $this->vendorWithSupplier();
        $booking = Booking::factory()->create();
        $booking->bookingSuppliers()->create(['supplier_id' => $supplier->id, 'status' => 'confirmed']);

        $mine = SupplierProduct::create(['supplier_id' => $supplier->id, 'name' => 'My bouquet', 'is_available' => true]);
        $theirs = SupplierProduct::create(['supplier_id' => Supplier::factory()->create()->id, 'name' => 'Their cake', 'is_available' => true]);
        $booking->bookingProducts()->create(['supplier_product_id' => $mine->id, 'quantity' => 3]);
        $booking->bookingProducts()->create(['supplier_product_id' => $theirs->id, 'quantity' => 1]);

        $this->actingAs($vendor)->get("/vendor/bookings/{$booking->id}")
            ->assertOk()->assertSee('My bouquet')->assertDontSee('Their cake');
    }
}
