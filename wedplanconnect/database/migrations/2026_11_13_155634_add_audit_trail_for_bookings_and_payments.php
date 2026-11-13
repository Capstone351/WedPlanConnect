<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records who changed what (and why) on bookings, and who recorded or voided each payment.
     */
    public function up(): void
    {
        Schema::create('booking_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('field', 50);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
        });

        // When an unconfirmed booking stops holding the planner's date. Bookings already waiting
        // get a full hold period from today rather than being released the moment this ships.
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('hold_expires_at')->nullable()->after('status');
        });
        DB::table('bookings')->where('status', 'pending')->update(['hold_expires_at' => now()->addDays(config('wedplan.pending_hold_days'))]);

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn('void_reason');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('hold_expires_at');
        });

        Schema::dropIfExists('booking_changes');
    }
};
