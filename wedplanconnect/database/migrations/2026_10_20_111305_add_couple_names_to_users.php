<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The couple's names, entered when a client registers and shown on their dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('bride_name', 100)->nullable()->after('name');
            $table->string('groom_name', 100)->nullable()->after('bride_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bride_name', 'groom_name']);
        });
    }
};
