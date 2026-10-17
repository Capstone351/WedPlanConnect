<?php

namespace Database\Seeders;

use App\Models\ClientBudget;
use App\Models\Supplier;
use App\Models\SupplierPreference;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * SAMPLE data for planner choice and favorites: planner bios, and a newly registered couple
 * who chose a planner, saved favorites and set a budget before having a booking.
 * Runs as part of DemoSeeder, or alone: php artisan db:seed --class=DemoPlannerChoiceSeeder
 */
class DemoPlannerChoiceSeeder extends Seeder
{
    public function run(): void
    {
        $bios = [
            'planner@fmtweddings.test' => 'Eight years styling garden and heritage weddings around Cebu. Loves fresh florals, soft color palettes, and calm, well-organized wedding days.',
            'joshua@fmtweddings.test' => 'Specializes in grand ballroom and beach celebrations, with a background in events lighting and sound. Known for big-day timelines that run on time.',
        ];

        foreach ($bios as $email => $bio) {
            User::where('email', $email)->update(['bio' => $bio]);
        }

        $joshua = User::where('email', 'joshua@fmtweddings.test')->first();
        if (! $joshua) {
            return;
        }

        $grace = User::firstOrCreate(
            ['email' => 'grace.lim.demo@gmail.com'],
            ['role' => 'client', 'name' => 'Grace Lim', 'phone' => '09171239876', 'password' => DemoSeeder::PASSWORD],
        );
        $grace->forceFill([
            'preferred_planner_id' => $joshua->id,
            'wedding_date' => today()->addMonths(8)->next('Saturday')->toDateString(),
            'wedding_date_alt' => today()->addMonths(8)->next('Saturday')->addWeek()->toDateString(),
            'wedding_venue' => 'Shangri-La Mactan Resort',
        ])->save();

        foreach (['Bethoven Photography', 'Scarlet Lights and Sounds Mobile'] as $name) {
            if ($supplier = Supplier::where('name', $name)->first()) {
                SupplierPreference::firstOrCreate(['user_id' => $grace->id, 'supplier_id' => $supplier->id]);
            }
        }

        $budget = ClientBudget::updateOrCreate(
            ['user_id' => $grace->id],
            ['total_amount' => 300000, 'guest_count' => 180, 'notes' => 'Beach or garden venue. Photography and lights are our top priorities.'],
        );
        $budget->allocations()->updateOrCreate(['category' => 'Photo & Video'], ['amount' => 50000]);
        $budget->allocations()->updateOrCreate(['category' => 'Lights & Sounds'], ['amount' => 40000]);
    }
}
