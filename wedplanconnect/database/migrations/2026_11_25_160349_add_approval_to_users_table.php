<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wedding Planners and Suppliers who sign up themselves wait for the Admin's approval before they can log in.
 * A supplier's business details are kept with the request until the Admin approves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('awaiting_approval')->default(false)->after('email_verified_at');
            $table->json('pending_supplier')->nullable()->after('awaiting_approval');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['awaiting_approval', 'pending_supplier']);
        });
    }
};
