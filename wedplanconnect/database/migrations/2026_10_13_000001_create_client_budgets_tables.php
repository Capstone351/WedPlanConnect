<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The couple's own wedding budget (total, guest count, optional breakdown by category),
 * set by the client so the Wedding Planner can plan within it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->decimal('total_amount', 12, 2);
            $table->unsignedSmallInteger('guest_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_budget_id')->constrained('client_budgets')->cascadeOnDelete();
            $table->string('category', 50);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['client_budget_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('client_budgets');
    }
};
