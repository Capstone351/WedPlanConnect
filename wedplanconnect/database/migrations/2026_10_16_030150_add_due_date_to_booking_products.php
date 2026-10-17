<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deadline for the vendor to have an ordered product ready, so suppliers can
 * prepare it ahead of the wedding. Existing orders default to the day before the event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_products', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('quantity');
        });

        DB::table('booking_products')->update([
            'due_date' => DB::raw('(SELECT DATE_SUB(event_date, INTERVAL 1 DAY) FROM bookings WHERE bookings.id = booking_products.booking_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('booking_products', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
