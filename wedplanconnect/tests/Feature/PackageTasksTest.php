<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_a_booking_turns_the_package_inclusions_into_tasks(): void
    {
        $planner = User::factory()->planner()->create();
        $client = User::factory()->create(['phone' => '09171234567']);
        $client->forceFill(['preferred_planner_id' => $planner->id, 'wedding_date' => today()->addYear(), 'wedding_package' => 'Package A'])->save();

        $this->actingAs($client)->post('/my/booking-request', ['venue' => 'Radisson Blu Hotel Cebu, Cebu City'])->assertSessionHasNoErrors();

        // A request the planner hasn't accepted yet doesn't fill their to-do list.
        $booking = Booking::sole();
        $this->assertSame(0, $booking->tasks()->count());

        $this->actingAs($planner)->patch("/bookings/{$booking->id}/confirm")->assertSessionHasNoErrors();
        $this->assertSame(count(Booking::packageTasks('Package A')), $booking->tasks()->count());

        $cake = $booking->tasks()->where('title', 'Order the wedding cake')->sole();
        $this->assertSame($planner->id, $cake->assigned_to);
        $this->assertSame(today()->addYear()->subDays(45)->toDateString(), $cake->due_date->toDateString());
        $this->assertStringStartsWith('Package A inclusion: Cake. Confirm the package', $cake->description);
        $this->assertSame(0, $booking->tasks()->where('description', 'like', '%Arrange and confirm it before the wedding%')->count());
        $this->assertTrue($booking->tasks()->where('title', 'Brief the 4 on-the-day coordinators')->exists());
        $this->assertTrue($booking->tasks()->where('title', "Prepare the bridesmaids' robes")->exists());

        $this->actingAs($planner)->get('/tasks')->assertOk()->assertSee('Plan the church decoration');
        $this->actingAs($client)->get('/my')->assertOk()->assertSee('Book the photo and video team');
    }

    public function test_changing_the_package_updates_untouched_tasks_and_keeps_worked_on_ones(): void
    {
        $booking = Booking::factory()->confirmed()->create(['package' => 'Package A', 'event_date' => today()->addYear()]);
        $booking->tasks()->where('title', 'Order the wedding cake')->update(['status' => 'completed']);
        $own = Task::create(['booking_id' => $booking->id, 'assigned_to' => $booking->planner_id, 'title' => 'Food tasting', 'due_date' => today()->addMonth()]);

        $booking->update(['package' => 'Package C']);

        $titles = $booking->tasks()->pluck('title');
        $this->assertContains('Brief the 2 on-the-day coordinators', $titles);
        $this->assertNotContains('Brief the 4 on-the-day coordinators', $titles);
        $this->assertNotContains("Prepare the bridesmaids' robes", $titles);
        $this->assertContains('Food tasting', $titles);
        $this->assertSame(1, $booking->tasks()->where('title', 'Order the wedding cake')->count());
        $this->assertSame('completed', $booking->tasks()->where('title', 'Order the wedding cake')->sole()->status);
        $this->assertModelExists($own);

        // A new wedding date moves the untouched due dates with it.
        $booking->update(['event_date' => today()->addYear()->addMonth()]);
        $this->assertSame(today()->addYear()->addMonth()->subDays(180)->toDateString(), $booking->tasks()->where('title', 'Book the reception venue')->sole()->due_date->toDateString());
    }

    public function test_a_close_wedding_date_never_gets_due_dates_in_the_past(): void
    {
        $booking = Booking::factory()->confirmed()->create(['package' => 'Package B', 'event_date' => today()->addWeek()]);

        $this->assertGreaterThan(0, $booking->tasks()->count());
        $this->assertSame(0, $booking->tasks()->whereDate('due_date', '<', today())->count());
        $this->assertSame(0, $booking->tasks()->whereDate('due_date', '>', today()->addWeek())->count());
    }

    public function test_priority_follows_importance_not_how_early_it_is_due(): void
    {
        $booking = Booking::factory()->confirmed()->create(['package' => 'Package A', 'event_date' => today()->addYear()]);
        $priority = $booking->tasks()->pluck('priority', 'title');

        $this->assertSame('high', $priority['Book the reception venue']);
        $this->assertSame('high', $priority['Brief the 4 on-the-day coordinators']);
        $this->assertSame('medium', $priority['Order the wedding cake']);
        $this->assertSame('low', $priority['Prepare the confetti and poppers']);
        $this->assertSame('low', $priority['Prepare the wine for the toast']);
    }

    public function test_existing_tasks_with_bare_inclusion_titles_are_reworded(): void
    {
        $booking = Booking::factory()->create(['package' => 'Package B']);
        $cake = Task::create(['booking_id' => $booking->id, 'assigned_to' => $booking->planner_id, 'title' => 'Cake', 'description' => 'Included in Package B. Arrange and confirm it before the wedding.', 'due_date' => today()->addMonth(), 'status' => 'completed']);
        $coordinators = Task::create(['booking_id' => $booking->id, 'assigned_to' => $booking->planner_id, 'title' => '3 On-the-Day Coordinators', 'due_date' => today()->addMonth()]);

        (require database_path('migrations/2026_11_14_114143_reword_package_tasks_as_actions.php'))->up();

        $this->assertSame('Order the wedding cake', $cake->fresh()->title);
        $this->assertSame('completed', $cake->fresh()->status);
        $this->assertStringStartsWith('Package B inclusion: Cake.', $cake->fresh()->description);
        $this->assertSame('Brief the 3 on-the-day coordinators', $coordinators->fresh()->title);
    }

    public function test_custom_packages_get_no_package_tasks(): void
    {
        $booking = Booking::factory()->confirmed()->create(['package' => 'Custom Package']);

        $this->assertSame(0, $booking->tasks()->count());
    }
}
