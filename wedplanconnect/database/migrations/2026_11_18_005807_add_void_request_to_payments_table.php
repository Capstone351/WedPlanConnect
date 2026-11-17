<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A planner can't void a payment; they ask the admin to, with a reason, and the admin decides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('void_requested_by')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('void_requested_at')->nullable()->after('void_requested_by');
            $table->string('void_request_reason', 500)->nullable()->after('void_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('void_requested_by');
            $table->dropColumn(['void_requested_at', 'void_request_reason']);
        });
    }
};
