<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Couples set their preferred wedding date (plus an optional backup date and venue)
 * before a booking exists, so their Wedding Planner knows when they want to marry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('wedding_date')->nullable()->after('bio');
            $table->date('wedding_date_alt')->nullable()->after('wedding_date');
            $table->string('wedding_venue', 200)->nullable()->after('wedding_date_alt');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wedding_date', 'wedding_date_alt', 'wedding_venue']);
        });
    }
};
