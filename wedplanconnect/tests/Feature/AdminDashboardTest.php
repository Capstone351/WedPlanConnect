<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seed_creates_only_the_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertSame('admin', User::first()->role);
        $this->assertSame(0, Booking::count());

        $this->actingAs(User::first())->get('/admin')->assertOk()->assertSee('Finish setting up WedPlanConnect');
    }

    public function test_figures_are_computed_live_and_accurately(): void
    {
        $admin = User::factory()->admin()->create();
        $planner = User::factory()->planner()->create();

        $future = Booking::factory()->create(['planner_id' => $planner->id, 'status' => 'confirmed', 'total_amount' => 100000, 'event_date' => today()->addMonth()]);
        $past = Booking::factory()->create(['planner_id' => $planner->id, 'status' => 'confirmed', 'total_amount' => 50000, 'event_date' => today()->subDays(3), 'client_name' => 'Past Couple']);
        Booking::factory()->create(['planner_id' => $planner->id, 'status' => 'cancelled', 'total_amount' => 80000]);

        Payment::create(['booking_id' => $future->id, 'amount' => 40000, 'payment_date' => today(), 'method' => 'cash']);

        // Overdue without the scheduler ever running.
        $task = Task::create(['booking_id' => $future->id, 'assigned_to' => $planner->id, 'title' => 'Order flowers', 'due_date' => today(), 'priority' => 'high', 'status' => 'pending']);
        $this->travel(1)->days();
        $this->assertTrue($task->fresh()->is_overdue);
        $this->assertSame(1, Task::overdue()->count());

        $view = $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Past Couple');

        $this->assertSame(1, $view->viewData('upcomingCounts')->sum());           // past & cancelled excluded
        $this->assertSame(40000.0, $view->viewData('collected'));
        $this->assertSame(110000.0, $view->viewData('outstanding'));              // 60k + 50k, cancelled excluded
        $this->assertSame(1, $view->viewData('overdueCount'));
        $this->assertTrue($view->viewData('awaitingClosure')->contains($past));
    }
}
