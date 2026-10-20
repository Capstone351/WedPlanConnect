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
            'Lorr Aljas' => [
                ['Bridal bouquet (roses & peonies)', 'Hand-tied bouquet in blush, ivory, and greenery. Matching boutonniere included.', 6500, 'per piece'],
                ['Entourage bouquets', 'Smaller hand-tied bouquets for bridesmaids and flower girls.', 1800, 'per piece'],
                ['Floral arch arrangement', 'Fresh floral installation for a circular or rectangular ceremony arch.', 28000, 'per set'],
                ['Aisle floral markers', 'Low arrangements along the aisle, set of 10.', 12000, 'per set'],
                ['Reception centerpieces', 'Low or tall centerpieces in your motif, per table.', 2500, 'per piece'],
            ],
            'Bethoven Photography' => [
                ['Wedding day photo coverage', 'Two photographers, 10 hours, 500+ edited photos, online gallery.', 45000, 'per package'],
                ['Prenup photoshoot', 'Half-day shoot at one Cebu location with 60 edited photos.', 18000, 'per package'],
                ['Same-day edit prints', '50 4R prints delivered at the reception.', 6000, 'per set'],
            ],
            'Scarlet Lights and Sounds Mobile' => [
                ['Basic lights & sounds', 'Two speakers, two wireless mics, and wash lights for ceremony and reception.', 25000, 'per event'],
                ['LED wall (8×12 ft)', 'P3 LED wall with operator for videos and live feed.', 30000, 'per event'],
                ['Fairy-light ceiling canopy', 'Draped warm-white lights over the reception area.', 15000, 'per set'],
            ],
            'Paul Flores & Team' => [
                ['Bridal hair & makeup', 'Airbrush makeup and hairstyling, with trial session.', 12000, 'per package'],
                ['Entourage hair & makeup', 'Per person, for mothers and bridesmaids.', 2500, 'per pax'],
            ],
            "Victoria's" => [
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

        // Sample stock counts so the vendor inventory shows normal, low and out-of-stock items.
        foreach (['Entourage bouquets' => 40, 'Reception centerpieces' => 60, 'Aisle floral markers' => 3, 'Floral arch arrangement' => 2] as $name => $stock) {
            SupplierProduct::where('name', $name)->whereNull('stock_quantity')->update(['stock_quantity' => $stock]);
        }

        // Sample packages: [supplier, name, description, guests, price, inclusions]
        $packages = [
            ['Lorr Aljas', 'Garden Romance styling package', 'Full floral and styling set-up for the ceremony and reception.', 100, 85000,
                "Bridal bouquet\n6 entourage bouquets\nCeremony arch florals\n10 aisle markers\nReception centerpieces for 10 tables"],
            ['Lorr Aljas', 'Grand Ballroom styling package', 'Ballroom-scale styling with stage backdrop and tall centerpieces.', 200, 160000,
                "Bridal and entourage bouquets\nStage backdrop with florals\nTall centerpieces for 20 tables\nAisle and entrance styling"],
            ["Victoria's", 'Wedding cake & dessert package', 'Wedding cake plus a dessert table for your guests.', 150, 28000,
                "3-tier wedding cake\nDessert table set-up\n150 cupcakes or pastries"],
            ['Scarlet Lights and Sounds Mobile', 'Reception lights & sounds package', 'Sound system, lighting and technician for the whole event.', 200, 45000,
                "4 speakers and 4 wireless mics\nWash and accent lights\nFairy-light ceiling canopy\nTechnician for the whole event"],
        ];

        foreach ($packages as [$supplierName, $name, $description, $guests, $price, $inclusions]) {
            if ($supplier = Supplier::where('name', $supplierName)->first()) {
                SupplierProduct::firstOrCreate(
                    ['supplier_id' => $supplier->id, 'name' => $name],
                    ['description' => $description, 'is_package' => true, 'guest_capacity' => $guests, 'inclusions' => $inclusions,
                        'price' => $price, 'unit' => 'per package', 'is_available' => true],
                );
            }
        }

        // Pick a few items for each confirmed or completed sample wedding.
        $picks = [
            ['Bridal bouquet (roses & peonies)', 1], ['Entourage bouquets', 6], ['Floral arch arrangement', 1],
            ['Reception centerpieces', 15], ['Wedding day photo coverage', 1], ['Bridal hair & makeup', 1],
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
