<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSupplier;
use App\Models\ChatbotSession;
use App\Models\Faq;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Every figure here is computed live from the database at request time.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $upcoming = Booking::upcoming();

        // Outstanding = unpaid remainder of every non-cancelled contract (never negative per booking).
        $outstanding = Booking::where('status', '!=', 'cancelled')->withSum('payments', 'amount')->get()
            ->sum(fn ($b) => max(0, (float) $b->total_amount - (float) $b->payments_sum_amount));
        $collected = (float) Payment::sum('amount');

        $chatThisMonth = ChatbotSession::where('created_at', '>=', now()->startOfMonth());

        $planners = User::where('role', 'planner')
            ->withCount([
                'plannedBookings as upcoming_count' => fn ($q) => $q->upcoming(),
                'plannedBookings as unclosed_count' => fn ($q) => $q->awaitingClosure(),
                'tasks as open_tasks_count' => fn ($q) => $q->open(),
                'tasks as overdue_tasks_count' => fn ($q) => $q->overdue(),
            ])
            ->orderByDesc('upcoming_count')->orderBy('name')->get();

        return view('admin.dashboard', [
            'userCounts' => User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
            'deactivatedCount' => User::onlyTrashed()->count(),
            'newUsersThisMonth' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            'recentUsers' => User::latest()->limit(5)->get(),
            'upcomingCounts' => (clone $upcoming)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statusCounts' => Booking::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'supplierStatuses' => BookingSupplier::whereHas('booking', fn ($q) => $q->upcoming())
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'collected' => $collected,
            'collectedThisMonth' => (float) Payment::whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'outstanding' => $outstanding,
            'collectionRate' => $collected + $outstanding > 0 ? (int) round($collected / ($collected + $outstanding) * 100) : 0,
            'revenueTrend' => $this->revenueTrend(),
            'upcoming' => (clone $upcoming)->orderBy('event_date')->with('planner')->limit(6)->get(),
            'awaitingClosure' => Booking::awaitingClosure()->with('planner')->orderBy('event_date')->get(),
            'planners' => $planners,
            // The action centre: things only the Admin can resolve.
            'plannersToRemind' => $planners->filter(fn ($p) => $p->overdue_tasks_count || $p->unclosed_count)->values(),
            'lockedUsers' => User::where('locked_until', '>', now())->orderBy('locked_until')->get(),
            'unlinkedVendors' => User::where('role', 'vendor')->whereDoesntHave('supplierProfile')->orderBy('name')->get(),
            'unansweredQuestions' => $this->unansweredQuestions(),
            'overdueTasks' => Task::overdue()->with('booking', 'assignee')->orderBy('due_date')->limit(5)->get(),
            'overdueCount' => Task::overdue()->count(),
            'openTasks' => Task::open()->count(),
            'lowStock' => InventoryItem::lowStock()->orderBy('quantity')->get(),
            'chatbot' => [
                'questions' => (clone $chatThisMonth)->count(),
                'faq' => (clone $chatThisMonth)->whereNotNull('faq_id')->count(),
                'ai' => (clone $chatThisMonth)->where('api_used', true)->count(),
                'faqCount' => Faq::count(),
            ],
            'setup' => array_filter([
                ['Add your Wedding Planner accounts', User::where('role', 'planner')->exists(), route('admin.users.create', ['role' => 'planner'])],
                ['Enter FMT’s real FAQ answers for WedBot', Faq::exists(), route('admin.faqs.create')],
            ], fn ($step) => ! $step[1]),
        ]);
    }

    /**
     * Recent questions WedBot could not answer, one per wording, so the Admin can add them as FAQs.
     *
     * @return Collection<int, ChatbotSession>
     */
    private function unansweredQuestions(): Collection
    {
        return ChatbotSession::whereNull('faq_id')->where('api_used', false)
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()->limit(100)->get()
            ->unique(fn (ChatbotSession $s) => mb_strtolower(trim($s->question)))
            ->take(5)->values();
    }

    /**
     * Payments received in each of the last six months, oldest first.
     *
     * @return Collection<int, array{label: string, value: float}>
     */
    private function revenueTrend(): Collection
    {
        $start = now()->startOfMonth()->subMonths(5);
        $byMonth = Payment::where('payment_date', '>=', $start)->get(['amount', 'payment_date'])
            ->groupBy(fn ($p) => $p->payment_date->format('Y-m'))
            ->map(fn ($payments) => (float) $payments->sum('amount'));

        return collect(range(0, 5))->map(function (int $i) use ($start, $byMonth) {
            $month = $start->copy()->addMonths($i);

            return ['label' => $month->format('M'), 'value' => $byMonth[$month->format('Y-m')] ?? 0.0];
        });
    }
}
