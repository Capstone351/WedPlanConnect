<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the order a planner sends to a supplier and the supplier's reply
 * (accepted = confirmed, declined = unavailable, with an optional note).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_suppliers', function (Blueprint $table) {
            $table->timestamp('order_sent_at')->nullable()->after('status');
            $table->timestamp('responded_at')->nullable()->after('order_sent_at');
            $table->string('response_note', 255)->nullable()->after('responded_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_suppliers', function (Blueprint $table) {
            $table->dropColumn(['order_sent_at', 'responded_at', 'response_note']);
        });
    }
};
