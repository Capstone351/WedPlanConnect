<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Accounts made before email verification existed keep working; only new sign-ups must verify.
     */
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    /**
     * Nothing to undo: verified dates are kept.
     */
    public function down(): void
    {
        //
    }
};
