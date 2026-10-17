<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public portfolio of past weddings ("Our Weddings") shown on the front page.
 * Entries are published only with the couple's documented consent (RA 10173).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('title', 150);
            $table->string('slug', 180)->unique();
            $table->date('event_date')->nullable();
            $table->string('venue', 200)->nullable();
            $table->string('theme', 100)->nullable();
            $table->string('summary', 300)->nullable();
            $table->text('story')->nullable();
            $table->text('testimonial')->nullable();
            $table->string('testimonial_by', 150)->nullable();
            $table->boolean('consent_confirmed')->default(false);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('portfolio_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_entry_id')->constrained('portfolio_entries')->cascadeOnDelete();
            $table->string('path', 255);
            $table->string('caption', 200)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_photos');
        Schema::dropIfExists('portfolio_entries');
    }
};
