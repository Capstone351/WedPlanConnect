<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorInventoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: Supplier, 2: SupplierProduct}
     */
    private function vendorWithProduct(int $stock = 10): array
    {
        $vendor = User::factory()->vendor()->create();
        $supplier = Supplier::factory()->create(['user_id' => $vendor->id]);
        $product = SupplierProduct::create(['supplier_id' => $supplier->id, 'name' => 'Centerpiece', 'price' => 2500, 'stock_quantity' => $stock, 'low_stock_threshold' => 3, 'is_available' => true]);

        return [$vendor, $supplier, $product];
    }

    public function test_units_ordered_for_upcoming_weddings_are_subtracted_from_stock(): void
    {
        [, , $product] = $this->vendorWithProduct(10);
        Booking::factory()->create(['status' => 'confirmed'])->bookingProducts()->create(['supplier_product_id' => $product->id, 'quantity' => 6]);
        Booking::factory()->create(['status' => 'cancelled'])->bookingProducts()->create(['supplier_product_id' => $product->id, 'quantity' => 4]);

        $this->assertSame(6, $product->reservedQuantity());
        $this->assertSame(4, $product->unitsLeft());
        $this->assertSame(['4 left', 'green'], $product->stockStatus());
    }

    public function test_vendors_manage_stock_from_my_products_not_a_separate_inventory_page(): void
    {
        [$vendor, , $product] = $this->vendorWithProduct(10);

        $this->actingAs($vendor)->get('/vendor/inventory')->assertNotFound();
        $this->actingAs($vendor)->get('/vendor')->assertOk()->assertDontSee('My Inventory');
        $this->actingAs($vendor)->get('/vendor/products')->assertOk()->assertSee('Centerpiece')->assertSee('of 10 in stock');
        $this->actingAs($vendor)->get("/vendor/products/{$product->id}/edit")->assertOk()->assertSee('id="stock_quantity"', false);
    }

    public function test_planner_cannot_order_more_than_is_left(): void
    {
        [, , $product] = $this->vendorWithProduct(5);
        $booking = Booking::factory()->create();
        $dueDate = $booking->event_date->toDateString();

        $this->actingAs($booking->planner)->post("/bookings/{$booking->id}/items", ['supplier_product_id' => $product->id, 'quantity' => 6, 'due_date' => $dueDate])
            ->assertSessionHasErrors(['quantity' => "Only 5 of Centerpiece left with {$product->supplier->name}."]);

        $this->actingAs($booking->planner)->post("/bookings/{$booking->id}/items", ['supplier_product_id' => $product->id, 'quantity' => 4, 'due_date' => $dueDate])
            ->assertSessionHasNoErrors();

        // Raising this wedding's own order from 4 to 5 only needs the 1 unit still free.
        $item = $booking->bookingProducts()->first();
        $this->actingAs($booking->planner)->patch("/bookings/{$booking->id}/items/{$item->id}", ['quantity' => 5])->assertSessionHasNoErrors();
        $this->actingAs($booking->planner)->patch("/bookings/{$booking->id}/items/{$item->id}", ['quantity' => 6])->assertSessionHasErrors('quantity');
    }
}
