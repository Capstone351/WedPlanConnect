<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingProduct;
use App\Models\BookingSupplier;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Database\Seeder;

/**
 * SAMPLE vendor products and wedding set-up selections, for demonstrations only.
 * Runs as part of DemoSeeder, or alone: php artisan db:seed --class=DemoProductsSeeder
 */
class DemoProductsSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'Bloom & Petal Florals' => [
                ['Bridal bouquet (roses & peonies)', 'Hand-tied bouquet in blush, ivory, and greenery. Matching boutonniere included.', 6500, 'per piece'],
                ['Entourage bouquets', 'Smaller hand-tied bouquets for bridesmaids and flower girls.', 1800, 'per piece'],
                ['Floral arch arrangement', 'Fresh floral installation for a circular or rectangular ceremony arch.', 28000, 'per set'],
                ['Aisle floral markers', 'Low arrangements along the aisle, set of 10.', 12000, 'per set'],
                ['Reception centerpieces', 'Low or tall centerpieces in your motif, per table.', 2500, 'per piece'],
            ],
            'Lumière Studio Cebu' => [
                ['Wedding day photo coverage', 'Two photographers, 10 hours, 500+ edited photos, online gallery.', 45000, 'per package'],
                ['Prenup photoshoot', 'Half-day shoot at one Cebu location with 60 edited photos.', 18000, 'per package'],
                ['Same-day edit prints', '50 4R prints delivered at the reception.', 6000, 'per set'],
            ],
            'Casa Verde Catering' => [
                ['Filipino-fusion buffet', 'Five main courses, rice, dessert, and drinks with uniformed waitstaff.', 950, 'per pax'],
                ['Plated dinner (3 courses)', 'Soup or salad, main course, and dessert.', 1350, 'per pax'],
                ['Grazing table', 'Cheese, cold cuts, fruits, and breads for 100 guests.', 22000, 'per set'],
            ],
            'Soundwave Events Cebu' => [
                ['Basic lights & sounds', 'Two speakers, two wireless mics, and wash lights for ceremony and reception.', 25000, 'per event'],
                ['LED wall (8×12 ft)', 'P3 LED wall with operator for videos and live feed.', 30000, 'per event'],
                ['Fairy-light ceiling canopy', 'Draped warm-white lights over the reception area.', 15000, 'per set'],
            ],
            'Glam Squad by Rica' => [
                ['Bridal hair & makeup', 'Airbrush makeup and hairstyling, with trial session.', 12000, 'per package'],
                ['Entourage hair & makeup', 'Per person, for mothers and bridesmaids.', 2500, 'per pax'],
            ],
            'Sugar & Lace Cakes' => [
                ['3-tier wedding cake', 'Fondant or buttercream finish, choice of two flavors.', 15000, 'per piece'],
                ['Dessert table', 'Cupcakes, macarons, and pastries for 100 guests.', 12000, 'per set'],
            ],
        ];

        foreach ($catalog as $supplierName => $products) {
            $supplier = Supplier::where('name', $supplierName)->first();

            foreach ($supplier ? $products : [] as [$name, $description, $price, $unit]) {
                SupplierProduct::firstOrCreate(
                    ['supplier_id' => $supplier->id, 'name' => $name],
                    ['description' => $description, 'price' => $price, 'unit' => $unit, 'is_available' => true],
                );
            }
        }

        // Pick a few items for each confirmed or completed sample wedding.
        $picks = [
            ['Bridal bouquet (roses & peonies)', 1], ['Entourage bouquets', 6], ['Floral arch arrangement', 1],
            ['Reception centerpieces', 15], ['Wedding day photo coverage', 1], ['Filipino-fusion buffet', 150],
            ['Basic lights & sounds', 1], ['3-tier wedding cake', 1],
        ];

        foreach (Booking::whereIn('status', ['confirmed', 'completed'])->get() as $booking) {
            foreach ($picks as [$name, $qty]) {
                $product = SupplierProduct::where('name', $name)->first();
                if (! $product) {
                    continue;
                }

                BookingProduct::firstOrCreate(
                    ['booking_id' => $booking->id, 'supplier_product_id' => $product->id],
                    ['quantity' => $qty, 'unit_price' => $product->price],
                );

                BookingSupplier::firstOrCreate(
                    ['booking_id' => $booking->id, 'supplier_id' => $product->supplier_id],
                    ['status' => 'pending'],
                );
            }
        }
    }
}
