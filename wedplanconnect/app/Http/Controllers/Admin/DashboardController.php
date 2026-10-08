<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSupplier;
use App\Models\Faq;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
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

        return view('admin.dashboard', [
            'userCounts' => User::selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role'),
            'upcomingCounts' => (clone $upcoming)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'supplierStatuses' => BookingSupplier::whereHas('booking', fn ($q) => $q->upcoming())
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'collected' => (float) Payment::sum('amount'),
            'collectedThisMonth' => (float) Payment::whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'outstanding' => $outstanding,
            'upcoming' => (clone $upcoming)->orderBy('event_date')->with('planner')->limit(6)->get(),
            'awaitingClosure' => Booking::awaitingClosure()->orderBy('event_date')->get(),
            'overdueTasks' => Task::overdue()->with('booking', 'assignee')->orderBy('due_date')->limit(6)->get(),
            'overdueCount' => Task::overdue()->count(),
            'openTasks' => Task::open()->count(),
            'lowStock' => InventoryItem::lowStock()->orderBy('quantity')->get(),
            'setup' => array_filter([
                ['Add your Wedding Planner accounts', User::where('role', 'planner')->exists(), route('admin.users.create')],
                ['Add your suppliers to the catalog', Supplier::exists(), route('suppliers.create')],
                ['Record your decoration inventory', InventoryItem::exists(), route('inventory.create')],
                ['Enter FMT’s real FAQ answers for WedBot', Faq::exists(), route('admin.faqs.create')],
            ], fn ($step) => ! $step[1]),
        ]);
    }
}
