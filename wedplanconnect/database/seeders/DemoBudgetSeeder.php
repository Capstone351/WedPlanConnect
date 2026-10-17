<?php

namespace Database\Seeders;

use App\Models\ClientBudget;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * SAMPLE budgets for demo couples. Runs as part of DemoSeeder,
 * or alone: php artisan db:seed --class=DemoBudgetSeeder
 */
class DemoBudgetSeeder extends Seeder
{
    public function run(): void
    {
        $budgets = [
            'client@fmtweddings.test' => [250000, 150, "We'd love lots of fresh flowers. We can cut back on the cake and favors if needed.", [
                'Event Styling' => 70000, 'Catering' => 110000, 'Photo & Video' => 40000, 'Cakes' => 10000,
            ]],
            'bea.lim@example.com' => [220000, 200, 'Grand ballroom feel, but please keep catering within budget.', [
                'Event Styling' => 60000, 'Catering' => 120000, 'Lights & Sounds' => 25000,
            ]],
        ];

        foreach ($budgets as $email => [$total, $guests, $notes, $split]) {
            $user = User::where('email', $email)->first();
            if (! $user) {
                continue;
            }

            $budget = ClientBudget::updateOrCreate(['user_id' => $user->id], ['total_amount' => $total, 'guest_count' => $guests, 'notes' => $notes]);
            foreach ($split as $category => $amount) {
                $budget->allocations()->updateOrCreate(['category' => $category], ['amount' => $amount]);
            }
        }
    }
}
