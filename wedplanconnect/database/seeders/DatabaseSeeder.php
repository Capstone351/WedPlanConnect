<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production seed: creates only the first administrator account. Everything else
 * (planners, clients, suppliers, inventory, FAQ answers, bookings) is entered by FMT
 * through the system, so the dashboards and reports reflect real records only.
 *
 * Set ADMIN_NAME, ADMIN_EMAIL and ADMIN_PASSWORD in .env before running `php artisan db:seed`.
 * For sample data instead, run: php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@fmtweddings.test');
        $password = env('ADMIN_PASSWORD', 'Password123');

        $admin = User::firstOrCreate(
            ['email' => $email],
            ['role' => 'admin', 'name' => env('ADMIN_NAME', 'FMT Administrator'), 'password' => $password],
        );

        if ($admin->wasRecentlyCreated) {
            $this->command?->info("Admin account created: {$email}");

            if (! env('ADMIN_PASSWORD')) {
                $this->command?->warn('Temporary password "Password123" was used. Log in and change it under My Account.');
            }
        }
    }
}
