<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ChatbotSession;
use App\Models\Faq;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PaymentRecorded;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        $booking = Booking::factory()->confirmed()->create(['total_amount' => 100000]);
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

    public function test_payments_can_only_be_recorded_once_the_booking_is_confirmed(): void
    {
        $booking = Booking::factory()->create(['total_amount' => 100000]);
        $planner = $booking->planner;

        $this->actingAs($planner)->post('/payments', ['booking_id' => $booking->id, 'amount' => 30000, 'method' => 'cash'])
            ->assertSessionHasErrors('booking_id');
        $this->assertSame(0, Payment::count());

        // The pending booking isn't offered in the form, and its page explains why.
        $this->actingAs($planner)->get("/payments?booking_id={$booking->id}")->assertOk()
            ->assertDontSee('<option value="'.$booking->id.'"', false)->assertSee("This booking isn't confirmed yet", false);
        $this->actingAs($planner)->get("/bookings/{$booking->id}")->assertSee('Available once the booking is confirmed.');

        $this->actingAs($planner)->patch("/bookings/{$booking->id}/confirm");
        $this->actingAs($planner)->post('/payments', ['booking_id' => $booking->id, 'amount' => 30000, 'method' => 'cash'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Payment::count());
    }

    public function test_payment_date_is_always_today_and_history_shows_newest_first(): void
    {
        $booking = Booking::factory()->confirmed()->create(['total_amount' => 100000]);
        $planner = $booking->planner;
        Payment::create(['booking_id' => $booking->id, 'amount' => 10000, 'payment_date' => today()->subMonth(), 'method' => 'cash', 'receipt_number' => 'OLD-1']);

        $this->actingAs($planner)->post('/payments', [
            'booking_id' => $booking->id, 'amount' => 20000, 'payment_date' => today()->subYear()->toDateString(), 'method' => 'gcash', 'receipt_number' => 'NEW-2',
        ])->assertSessionHasNoErrors();

        $latest = Payment::where('receipt_number', 'NEW-2')->sole();
        $this->assertSame(today()->toDateString(), $latest->payment_date->toDateString());

        $this->actingAs($planner)->get("/payments?booking_id={$booking->id}")->assertOk()
            ->assertDontSee('id="payment_date"', false)->assertSeeInOrder(['NEW-2', 'OLD-1'])->assertDontSee('/edit"', false);
    }

    public function test_recorded_payments_cannot_be_edited(): void
    {
        $booking = Booking::factory()->create(['total_amount' => 100000]);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 10000, 'payment_date' => today(), 'method' => 'cash']);

        $this->actingAs($booking->planner)->get("/payments/{$payment->id}/edit")->assertNotFound();
        $this->actingAs($booking->planner)->put("/payments/{$payment->id}", ['amount' => 1, 'method' => 'cash'])->assertMethodNotAllowed();
        $this->assertSame(10000.0, (float) $payment->fresh()->amount);
    }

    public function test_couple_is_notified_of_every_recorded_payment_with_its_details(): void
    {
        $booking = Booking::factory()->confirmed()->create(['total_amount' => 100000, 'client_name' => 'Cha & Ethan']);

        $this->actingAs($booking->planner)->post('/payments', [
            'booking_id' => $booking->id, 'amount' => 30000, 'method' => 'gcash', 'receipt_number' => 'OR-123', 'notes' => 'Down payment',
        ])->assertSessionHasNoErrors();

        $notification = $booking->client->notifications()->sole();
        $this->assertSame(PaymentRecorded::class, $notification->type);
        $this->assertSame('Payment received: ₱30,000.00', $notification->data['title']);
        foreach (['GCash', today()->format('F j, Y'), 'OR-123', 'Down payment', $booking->planner->name, '₱100,000.00', 'Remaining balance: ₱70,000.00'] as $detail) {
            $this->assertStringContainsString($detail, $notification->data['body']);
        }

        $mail = (new PaymentRecorded(Payment::sole(), $booking->planner->name))->toMail($booking->client)->render();
        $this->assertStringContainsString('OR-123', (string) $mail);
        $this->assertStringContainsString('Cha &amp; Ethan', (string) $mail);
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

    public function test_chatbot_uses_backup_gemini_model_and_returns_plain_text(): void
    {
        config(['wedplan.chatbot.gemini_key' => 'test-key', 'wedplan.chatbot.gemini_model' => 'main', 'wedplan.chatbot.gemini_backup_model' => 'backup']);
        Http::fake([
            '*/models/main:*' => Http::response(['error' => ['message' => 'high demand']], 503),
            '*/models/backup:*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => "Try **coral** and *white*:\n* Sunset tones"]]]]]]),
        ]);
        Supplier::factory()->create(['category' => 'Entourage Dress(es)', 'availability' => 'available']);

        $this->postJson('/chatbot/ask', ['message' => 'What colors suit a beach wedding?'])
            ->assertOk()->assertExactJson(['source' => 'ai', 'answer' => "Try coral and white:\n• Sunset tones"]);
    }

    public function test_reports_respect_role_scope(): void
    {
        $planner = User::factory()->planner()->create();
        Booking::factory()->create(['planner_id' => $planner->id]);

        $this->actingAs($planner)->get('/reports/booking-status')->assertForbidden();
        $this->actingAs($planner)->get('/reports/daily-operations')->assertOk();
        $this->actingAs($planner)->get('/reports/daily-operations/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_client_payment_history_lists_the_most_recent_payment_first(): void
    {
        $admin = User::factory()->admin()->create();
        $older = Booking::factory()->create(['client_name' => 'Ana Cruz', 'status' => 'confirmed']);
        $newer = Booking::factory()->create(['client_name' => 'Zoe Lim', 'status' => 'confirmed']);
        Booking::factory()->create(['client_name' => 'Ben Uy', 'status' => 'confirmed']);
        Payment::create(['booking_id' => $older->id, 'amount' => 1000, 'payment_date' => today()->subDays(5), 'method' => 'cash']);
        Payment::create(['booking_id' => $newer->id, 'amount' => 1000, 'payment_date' => today(), 'method' => 'cash']);

        $rows = app(ReportService::class)->build('client-payment-history', $admin, null, null)['rows'];

        $this->assertSame(['Zoe Lim', 'Ana Cruz', 'Ben Uy'], array_column($rows, 0));
    }
}
