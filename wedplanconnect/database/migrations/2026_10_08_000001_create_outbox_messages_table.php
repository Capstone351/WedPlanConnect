<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test-mode email outbox: copies of emails the app "sent" while MAIL_MAILER=log,
 * so staff can read one-time PINs during demos. Not used once real email is configured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('to', 255);
            $table->string('subject', 255);
            $table->longText('html')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
