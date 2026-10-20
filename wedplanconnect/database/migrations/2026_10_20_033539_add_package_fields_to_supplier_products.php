<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets suppliers list packages (a bundle of items for a number of guests)
 * alongside single products in the catalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->boolean('is_package')->default(false)->after('description');
            $table->unsignedSmallInteger('guest_capacity')->nullable()->after('is_package');  // "good for N guests"
            $table->text('inclusions')->nullable()->after('guest_capacity');                   // one item per line
        });
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropColumn(['is_package', 'guest_capacity', 'inclusions']);
        });
    }
};
