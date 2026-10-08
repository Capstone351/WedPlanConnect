<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor offerings (supplies, products, services) and the items a planner selects for each booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('unit', 30)->nullable();
            $table->boolean('is_available')->default(true);
            $table->string('image_path', 255)->nullable();   // stored on the private disk, outside the web root
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('booking_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->foreignId('supplier_product_id')->constrained('supplier_products');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->nullable();  // price at the time it was selected
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'supplier_product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_products');
        Schema::dropIfExists('supplier_products');
    }
};
