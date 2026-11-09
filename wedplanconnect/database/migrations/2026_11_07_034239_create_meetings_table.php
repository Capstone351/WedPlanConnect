<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meetings a couple asks to have with their Wedding Planner. The planner confirms or declines them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users');
            $table->foreignId('planner_id')->constrained('users');
            $table->dateTime('scheduled_at')->index();
            $table->enum('mode', ['in_person', 'online'])->default('in_person');
            $table->string('location', 200)->nullable();
            $table->string('agenda', 500)->nullable();
            $table->enum('status', ['requested', 'confirmed', 'declined', 'cancelled'])->default('requested');
            $table->timestamp('responded_at')->nullable();
            $table->string('response_note', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
