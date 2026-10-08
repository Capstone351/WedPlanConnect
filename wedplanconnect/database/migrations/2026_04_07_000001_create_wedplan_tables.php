<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core WedPlanConnect schema — follows Table 19 (Data Dictionary) of the manuscript.
 *
 * Additions beyond the dictionary, each required by a use case or module description:
 *  - suppliers.user_id / description / starting_price / availability  (vendor profile + UC-07 catalog)
 *  - supplier_preferences                                             (UC-07 client preference indication)
 *  - bookings.otp_attempts                                            (brute-force limit on the QR OTP)
 *  - tasks.priority                                                   (UC-05 task priority)
 *  - inventory_items.unit_cost, inventory_movements                   (Inventory Movement Report)
 *  - faqs.keywords, chatbot_sessions.faq_id                           (keyword matching + unanswered-question audit)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('category', 50)->index();
            $table->string('phone', 20);
            $table->string('email', 150)->nullable();
            $table->text('description')->nullable();
            $table->decimal('starting_price', 10, 2)->nullable();
            $table->enum('availability', ['available', 'unavailable'])->default('available');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planner_id')->constrained('users');
            $table->foreignId('client_id')->constrained('users');
            $table->string('client_name', 150);
            $table->string('contact_number', 20);
            $table->date('event_date')->index();
            $table->string('venue', 200);
            $table->string('package', 100);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'completed', 'cancelled'])->default('pending');
            $table->string('qr_token', 64)->nullable()->unique();
            $table->string('otp_code', 64)->nullable();       // SHA-256 hash of the 6-digit PIN, never the PIN itself
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('booking_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->enum('status', ['pending', 'contacted', 'confirmed', 'unavailable'])->default('pending');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('supplier_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'supplier_id']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings');
            $table->foreignId('assigned_to')->constrained('users');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['pending', 'ongoing', 'completed'])->default('pending');
            $table->boolean('is_overdue')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 50)->index();
            $table->unsignedInteger('quantity')->default(0);
            $table->string('unit', 20);
            $table->unsignedInteger('min_threshold')->default(0);
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->enum('availability', ['available', 'out_of_stock'])->default('available');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items');
            $table->foreignId('booking_id')->nullable()->constrained('bookings');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->integer('change');
            $table->unsignedInteger('quantity_after');
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings');
            $table->decimal('amount', 10, 2);
            $table->date('payment_date');
            $table->enum('method', ['cash', 'gcash', 'bank_transfer']);
            $table->string('receipt_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->index();
            $table->text('question');
            $table->text('answer');
            $table->string('keywords', 255)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('chatbot_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('faq_id')->nullable()->constrained('faqs');
            $table->text('question');
            $table->text('answer');
            $table->boolean('api_used')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_sessions');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('supplier_preferences');
        Schema::dropIfExists('booking_suppliers');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('suppliers');
    }
};
