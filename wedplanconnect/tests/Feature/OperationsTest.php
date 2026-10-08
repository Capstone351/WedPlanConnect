<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ChatbotSession;
use App\Models\Faq;
use App\Models\InventoryItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inventory, payments, tasks, chatbot, and reports.
 */
class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_availability_updates_automatically_and_logs_movements(): void
    {
        $planner = User::factory()->planner()->create();
        $item = InventoryItem::create(['name' => 'Vases', 'category' => 'Centerpieces', 'quantity' => 3, 'unit' => 'pcs', 'min_threshold' => 5]);

        $this->assertSame('available', $item->availability);
        $this->assertTrue($item->isLowStock());

        $this->actingAs($planner)->post("/inventory/{$item->id}/adjust", ['direction' => 'out', 'amount' => 3])
            ->assertRedirect()->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame(0, $item->quantity);
        $this->assertSame('out_of_stock', $item->availability);
        $this->assertSame(2, $item->movements()->count());
        $this->assertSame(-3, $item->movements()->latest('id')->first()->change);
    }

    public function test_inventory_cannot_go_negative(): void
    {
        $planner = User::factory()->planner()->create();
        $item = InventoryItem::create(['name' => 'Vases', 'category' => 'Centerpieces', 'quantity' => 2, 'unit' => 'pcs', 'min_threshold' => 1]);

        $this->actingAs($planner)->post("/inventory/{$item->id}/adjust", ['direction' => 'out', 'amount' => 5])->assertSessionHasErrors('amount');
        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_payments_recalculate_balance_and_block_overpayment(): void
    {
        $booking = Booking::factory()->create(['total_amount' => 100000]);
        $planner = $booking->planner;

        $this->actingAs($planner)->post('/payments', [
            'booking_id' => $booking->id, 'amount' => 30000, 'payment_date' => today()->toDateString(), 'method' => 'gcash',
        ])->assertSessionHasNoErrors();

        $this->assertSame(70000.0, $booking->balance());
        $this->assertSame('Partial', $booking->paymentStatus());

        $this->actingAs($planner)->post('/payments', [
            'booking_id' => $booking->id, 'amount' => 70000.01, 'payment_date' => today()->toDateString(), 'method' => 'cash',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($planner)->post('/payments', [
            'booking_id' => $booking->id, 'amount' => 70000, 'payment_date' => today()->toDateString(), 'method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Paid', $booking->paymentStatus());
    }

    public function test_contract_pdf_downloads(): void
    {
        $booking = Booking::factory()->create();

        $this->actingAs($booking->planner)->get("/bookings/{$booking->id}/contract.pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_scheduler_command_flags_overdue_tasks(): void
    {
        $planner = User::factory()->planner()->create();
        $task = Task::create(['assigned_to' => $planner->id, 'title' => 'Call florist', 'due_date' => today(), 'priority' => 'high', 'status' => 'pending']);
        $this->assertFalse($task->is_overdue);

        $this->travel(2)->days();
        $this->artisan('tasks:flag-overdue')->assertSuccessful();
        $this->assertTrue($task->fresh()->is_overdue);

        $task->fresh()->update(['status' => 'completed']);
        $this->assertFalse($task->fresh()->is_overdue);
    }

    public function test_chatbot_matches_faq_and_logs_session(): void
    {
        $faq = Faq::create(['category' => 'Payments', 'question' => 'What payment methods do you accept?', 'answer' => 'Cash, GCash, and bank transfer.', 'keywords' => 'gcash, bank']);
        $client = User::factory()->create();

        $this->actingAs($client)->postJson('/chatbot/ask', ['message' => 'Do you accept GCash payment?'])
            ->assertOk()->assertJson(['source' => 'faq', 'answer' => 'Cash, GCash, and bank transfer.']);

        $this->assertDatabaseHas('chatbot_sessions', ['user_id' => $client->id, 'faq_id' => $faq->id, 'api_used' => false]);
    }

    public function test_chatbot_falls_back_without_gemini_key(): void
    {
        config(['wedplan.chatbot.gemini_key' => null]);
        Faq::create(['category' => 'Payments', 'question' => 'What payment methods do you accept?', 'answer' => 'Cash.']);

        $this->postJson('/chatbot/ask', ['message' => 'Can you recommend a honeymoon destination in Europe?'])
            ->assertOk()->assertJson(['source' => 'fallback']);

        $this->assertSame(1, ChatbotSession::whereNull('faq_id')->where('api_used', false)->count());
    }

    public function test_reports_respect_role_scope(): void
    {
        $admin = User::factory()->admin()->create();
        $planner = User::factory()->planner()->create();
        Booking::factory()->create(['planner_id' => $planner->id]);

        foreach (array_keys(\App\Services\ReportService::TYPES) as $type) {
            $this->actingAs($admin)->get("/reports/{$type}")->assertOk();
        }

        $this->actingAs($planner)->get('/reports/booking-status')->assertForbidden();
        $this->actingAs($planner)->get('/reports/daily-operations')->assertOk();
        $this->actingAs($admin)->get('/reports/client-payment-history/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
