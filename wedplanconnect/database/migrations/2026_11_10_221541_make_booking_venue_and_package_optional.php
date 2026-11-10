<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Couples can send their booking before the venue and package are decided (they're discussed
 * with the planner at a meeting). The planner fills them in before confirming the booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('venue', 200)->nullable()->change();
            $table->string('package', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('venue', 200)->nullable(false)->default('')->change();
            $table->string('package', 100)->nullable(false)->default('')->change();
        });
    }
};
