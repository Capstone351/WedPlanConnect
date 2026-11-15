<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ChatbotSession;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PlannerReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminActionCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_what_only_the_admin_can_resolve(): void
    {
        $admin = User::factory()->admin()->create();
        $locked = User::factory()->create(['name' => 'Locked Couple']);
        $locked->forceFill(['locked_until' => now()->addMinutes(10)])->save();
        $vendor = User::factory()->vendor()->create(['name' => 'Unlinked Florist']);
        $planner = User::factory()->planner()->create(['name' => 'Behind Planner']);
        Task::create(['assigned_to' => $planner->id, 'title' => 'Call florist', 'due_date' => today()->subDays(2), 'status' => 'pending']);
        ChatbotSession::create(['question' => 'Do you do destination weddings?', 'answer' => 'Sorry.', 'api_used' => false]);
        ChatbotSession::create(['question' => 'do you do destination weddings? ', 'answer' => 'Sorry.', 'api_used' => false]);

        $page = $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('4 to do')
            ->assertSee("Locked Couple's account is locked", false)->assertSee(route('admin.users.unlock', $locked))
            ->assertSee('Behind Planner is falling behind')->assertSee('1 overdue task')
            ->assertSee("Unlinked Florist can't manage products yet", false)->assertSee(route('admin.users.edit', $vendor))
            ->assertSee('destination weddings?')->assertSee(route('admin.faqs.create').'?question=', false);

        $this->assertCount(1, $page->viewData('unansweredQuestions'));

        $this->actingAs($admin)->get('/admin/faqs/create?question=Do+you+do+destination+weddings%3F')
            ->assertOk()->assertSee('value="Do you do destination weddings?"', false);
    }

    public function test_dashboard_says_all_clear_when_nothing_needs_the_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()
            ->assertSee('All clear')->assertSee('Nothing needs you right now');
    }

    public function test_admin_reminds_a_planner_about_overdue_tasks_and_open_past_weddings(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['name' => 'Fe Admin']);
        $planner = User::factory()->planner()->create();
        Booking::factory()->create(['planner_id' => $planner->id, 'status' => 'confirmed', 'event_date' => today()->subDays(3), 'client_name' => 'Past Couple']);
        Task::create(['assigned_to' => $planner->id, 'title' => 'Order flowers', 'due_date' => today()->subDay(), 'status' => 'pending']);

        $this->actingAs($admin)->post("/admin/planners/{$planner->id}/remind")->assertSessionHas('status', "Reminder sent to {$planner->name}.");

        Notification::assertSentTo($planner, PlannerReminder::class, function (PlannerReminder $n) use ($planner) {
            $body = $n->toArray($planner)['body'];

            return $n->toArray($planner)['title'] === 'Reminder from Fe Admin'
                && str_contains($body, "Past Couple's wedding") && str_contains($body, 'Overdue task: Order flowers');
        });
    }

    public function test_only_planners_with_something_overdue_get_a_reminder_and_only_the_admin_sends_it(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $planner = User::factory()->planner()->create();

        $this->actingAs($admin)->post("/admin/planners/{$planner->id}/remind")->assertSessionHas('status', "{$planner->name} has nothing to follow up on.");
        $this->actingAs($admin)->post('/admin/planners/'.User::factory()->create()->id.'/remind')->assertNotFound();
        $this->actingAs(User::factory()->planner()->create())->post("/admin/planners/{$planner->id}/remind")->assertForbidden();

        Notification::assertNothingSent();
    }
}
