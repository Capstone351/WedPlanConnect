<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Favorite suppliers now belong to the couple's account (user_id), so couples can save
 *   favorites before their planner creates the booking. booking_id becomes optional.
 * - Couples can choose their preferred Wedding Planner (users.preferred_planner_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });

        // Existing favorites: copy the couple from their booking.
        DB::table('supplier_preferences')
            ->join('bookings', 'bookings.id', '=', 'supplier_preferences.booking_id')
            ->update(['supplier_preferences.user_id' => DB::raw('bookings.client_id')]);

        // A couple keeps one favorite per supplier.
        $keep = DB::table('supplier_preferences')->selectRaw('MIN(id) as id')->groupBy('user_id', 'supplier_id')->pluck('id');
        DB::table('supplier_preferences')->whereNotIn('id', $keep)->delete();

        Schema::table('supplier_preferences', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->change();
            $table->unique(['user_id', 'supplier_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('preferred_planner_id')->nullable()->after('phone')->constrained('users')->nullOnDelete();
            $table->string('bio', 500)->nullable()->after('preferred_planner_id');   // planners' short introduction
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('preferred_planner_id');
            $table->dropColumn('bio');
        });

        Schema::table('supplier_preferences', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'supplier_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
