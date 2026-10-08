<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingSupplier;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the strategic and operational reports of the Reporting Module (UC-09).
 * Each report returns: title, description, columns (label => align), rows, summary.
 */
class ReportService
{
    public const TYPES = [
        // Strategic — Admin only
        'booking-status' => ['Booking Status Summary', 'strategic', 'All bookings by status with contract and collected amounts.'],
        'payment-collection' => ['Payment Collection Summary', 'strategic', 'Payments received in the period, by method, against outstanding balances.'],
        'supplier-performance' => ['Supplier Performance', 'strategic', 'Assignments and confirmation rate per supplier.'],
        'task-completion' => ['Task Completion Summary', 'strategic', 'Task completion and overdue rates per planner.'],
        'inventory-alerts' => ['Inventory Stock Alerts', 'strategic', 'Items at or below their minimum threshold.'],
        // Operational — Admin and Wedding Planner (scoped to own bookings)
        'daily-operations' => ['Daily Operations Report', 'operational', 'Active bookings with upcoming event dates, open tasks, and supplier readiness.'],
        'weekly-supplier-coordination' => ['Weekly Supplier Coordination', 'operational', 'Supplier confirmation statuses and unresolved coordination items per active booking.'],
        'task-status' => ['Task Status Report', 'operational', 'Tasks by status with overdue flags and assigned planners.'],
        'inventory-movement' => ['Inventory Movement Report', 'operational', 'Stock-level changes, low-stock alerts, and material commitments per booking.'],
        'client-payment-history' => ['Client Payment History', 'operational', 'Payments received, outstanding balances, and contract amounts per client booking.'],
    ];

    public static function allowedFor(User $user): array
    {
        return array_filter(self::TYPES, fn ($t) => $user->role === 'admin' || $t[1] === 'operational');
    }

    public function build(string $type, User $user, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        [$title, $kind, $description] = self::TYPES[$type];

        $report = match ($type) {
            'booking-status' => $this->bookingStatus($user, $from, $to),
            'payment-collection' => $this->paymentCollection($user, $from, $to),
            'supplier-performance' => $this->supplierPerformance($user, $from, $to),
            'task-completion' => $this->taskCompletion($user, $from, $to),
            'inventory-alerts' => $this->inventoryAlerts(),
            'daily-operations' => $this->dailyOperations($user, $from, $to),
            'weekly-supplier-coordination' => $this->supplierCoordination($user, $from, $to),
            'task-status' => $this->taskStatus($user, $from, $to),
            'inventory-movement' => $this->inventoryMovement($user, $from, $to),
            'client-payment-history' => $this->clientPaymentHistory($user, $from, $to),
        };

        return $report + compact('title', 'kind', 'description', 'type');
    }

    private function bookings(User $user, ?CarbonInterface $from, ?CarbonInterface $to): Builder
    {
        return Booking::visibleTo($user)
            ->when($from, fn ($q) => $q->whereDate('event_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('event_date', '<=', $to));
    }

    private function bookingStatus(User $user, $from, $to): array
    {
        $bookings = $this->bookings($user, $from, $to)->withSum('payments', 'amount')->orderBy('event_date')->get();

        return [
            'columns' => ['#' => 'left', 'Client' => 'left', 'Event Date' => 'left', 'Venue' => 'left', 'Package' => 'left', 'Status' => 'left', 'Contract' => 'right', 'Paid' => 'right'],
            'rows' => $bookings->map(fn ($b) => [
                $b->id, $b->client_name, $b->event_date->format('M j, Y'), $b->venue, $b->package, ucfirst($b->status),
                $this->money($b->total_amount), $this->money($b->payments_sum_amount),
            ])->all(),
            'summary' => collect(Booking::STATUSES)->mapWithKeys(fn ($s) => [ucfirst($s) => $bookings->where('status', $s)->count()])->all()
                + ['Total Contracted (excl. cancelled)' => $this->money($bookings->where('status', '!=', 'cancelled')->sum('total_amount'))],
        ];
    }

    private function paymentCollection(User $user, $from, $to): array
    {
        $payments = Payment::whereIn('booking_id', Booking::visibleTo($user)->select('id'))
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->with('booking:id,client_name')
            ->orderBy('payment_date')->get();

        $outstanding = Booking::visibleTo($user)->where('status', '!=', 'cancelled')->withSum('payments', 'amount')->get()
            ->sum(fn ($b) => max(0, (float) $b->total_amount - (float) $b->payments_sum_amount));

        return [
            'columns' => ['Date' => 'left', 'Booking' => 'left', 'Client' => 'left', 'Method' => 'left', 'Receipt #' => 'left', 'Amount' => 'right'],
            'rows' => $payments->map(fn ($p) => [
                $p->payment_date->format('M j, Y'), '#'.$p->booking_id, $p->booking?->client_name, $p->methodLabel(), $p->receipt_number ?: '—', $this->money($p->amount),
            ])->all(),
            'summary' => ['Total Collected' => $this->money($payments->sum('amount'))]
                + collect(Payment::METHODS)->mapWithKeys(fn ($label, $key) => [$label => $this->money($payments->where('method', $key)->sum('amount'))])->all()
                + ['Outstanding Balances (all active contracts)' => $this->money($outstanding)],
        ];
    }

    private function supplierPerformance(User $user, $from, $to): array
    {
        $bookingIds = $this->bookings($user, $from, $to)->select('id');
        $assignments = BookingSupplier::whereIn('booking_id', $bookingIds)->get()->groupBy('supplier_id');
        $suppliers = Supplier::withTrashed()->whereIn('id', $assignments->keys())->orderBy('category')->orderBy('name')->get();

        $rows = $suppliers->map(function ($s) use ($assignments) {
            $a = $assignments[$s->id];
            $confirmed = $a->where('status', 'confirmed')->count();

            return [
                $s->name, $s->category, $a->count(), $confirmed, $a->where('status', 'contacted')->count(),
                $a->where('status', 'pending')->count(), $a->where('status', 'unavailable')->count(),
                round($confirmed / max(1, $a->count()) * 100).'%',
            ];
        });

        $all = $assignments->flatten();

        return [
            'columns' => ['Supplier' => 'left', 'Category' => 'left', 'Assigned' => 'right', 'Confirmed' => 'right', 'Contacted' => 'right', 'Pending' => 'right', 'Unavailable' => 'right', 'Confirm Rate' => 'right'],
            'rows' => $rows->all(),
            'summary' => [
                'Suppliers engaged' => $suppliers->count(),
                'Total assignments' => $all->count(),
                'Overall confirmation rate' => round($all->where('status', 'confirmed')->count() / max(1, $all->count()) * 100).'%',
            ],
        ];
    }

    private function tasks(User $user, $from, $to): Builder
    {
        return Task::visibleTo($user)
            ->when($from, fn ($q) => $q->whereDate('due_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('due_date', '<=', $to));
    }

    private function taskCompletion(User $user, $from, $to): array
    {
        $tasks = $this->tasks($user, $from, $to)->with('assignee:id,name')->get();

        $rows = $tasks->groupBy('assigned_to')->map(function ($group) {
            $done = $group->where('status', 'completed')->count();

            return [
                $group->first()->assignee?->name ?? 'Unassigned', $group->count(), $done,
                $group->where('status', 'ongoing')->count(), $group->where('status', 'pending')->count(),
                $group->where('is_overdue', true)->where('status', '!=', 'completed')->count(),
                round($done / max(1, $group->count()) * 100).'%',
            ];
        })->sortBy(0)->values();

        return [
            'columns' => ['Planner' => 'left', 'Total' => 'right', 'Completed' => 'right', 'Ongoing' => 'right', 'Pending' => 'right', 'Overdue' => 'right', 'Completion' => 'right'],
            'rows' => $rows->all(),
            'summary' => [
                'Total tasks' => $tasks->count(),
                'Completed' => $tasks->where('status', 'completed')->count(),
                'Overdue (open)' => $tasks->where('is_overdue', true)->where('status', '!=', 'completed')->count(),
                'Completion rate' => round($tasks->where('status', 'completed')->count() / max(1, $tasks->count()) * 100).'%',
            ],
        ];
    }

    private function inventoryAlerts(): array
    {
        $items = InventoryItem::lowStock()->orderBy('quantity')->get();

        return [
            'columns' => ['Item' => 'left', 'Category' => 'left', 'In Stock' => 'right', 'Unit' => 'left', 'Min. Threshold' => 'right', 'Status' => 'left'],
            'rows' => $items->map(fn ($i) => [$i->name, $i->category, $i->quantity, $i->unit, $i->min_threshold, $i->stockLabel()])->all(),
            'summary' => [
                'Items at/below threshold' => $items->count(),
                'Out of stock' => $items->where('quantity', 0)->count(),
                'Total items tracked' => InventoryItem::count(),
            ],
        ];
    }

    private function dailyOperations(User $user, $from, $to): array
    {
        $from ??= today();
        $to ??= today()->addDays(30);

        $bookings = $this->bookings($user, $from, $to)->active()
            ->withCount(['tasks as open_tasks' => fn ($q) => $q->open(), 'bookingSuppliers as suppliers_total', 'bookingSuppliers as suppliers_confirmed' => fn ($q) => $q->where('status', 'confirmed')])
            ->withSum('payments', 'amount')
            ->orderBy('event_date')->get();

        $dueToday = Task::visibleTo($user)->open()->whereDate('due_date', today())->count();

        return [
            'columns' => ['Event Date' => 'left', 'Days to Go' => 'right', 'Client' => 'left', 'Venue' => 'left', 'Status' => 'left', 'Open Tasks' => 'right', 'Suppliers Confirmed' => 'right', 'Balance' => 'right'],
            'rows' => $bookings->map(fn ($b) => [
                $b->event_date->format('D, M j, Y'), $b->daysToGo(), $b->client_name, $b->venue, ucfirst($b->status),
                $b->open_tasks, "{$b->suppliers_confirmed} / {$b->suppliers_total}",
                $this->money((float) $b->total_amount - (float) $b->payments_sum_amount),
            ])->all(),
            'summary' => [
                'Active bookings in window' => $bookings->count(),
                'Tasks due today' => $dueToday,
                'Overdue tasks' => Task::visibleTo($user)->overdue()->count(),
                'Low-stock items' => InventoryItem::lowStock()->count(),
            ],
            'period' => [$from, $to],
        ];
    }

    private function supplierCoordination(User $user, $from, $to): array
    {
        $bookingIds = $this->bookings($user, $from, $to)->active()->select('id');
        $assignments = BookingSupplier::whereIn('booking_id', $bookingIds)
            ->with(['booking:id,client_name,event_date', 'supplier'])
            ->get()
            ->sortBy([fn ($a, $b) => $a->booking->event_date <=> $b->booking->event_date, fn ($a, $b) => strcmp($a->status, $b->status)]);

        return [
            'columns' => ['Event Date' => 'left', 'Client' => 'left', 'Supplier' => 'left', 'Category' => 'left', 'Status' => 'left', 'Last Update' => 'left'],
            'rows' => $assignments->map(fn ($a) => [
                $a->booking->event_date->format('M j, Y'), $a->booking->client_name, $a->supplier->name, $a->supplier->category,
                ucfirst($a->status), $a->updated_at->diffForHumans(),
            ])->values()->all(),
            'summary' => collect(BookingSupplier::STATUSES)->mapWithKeys(fn ($s) => [ucfirst($s) => $assignments->where('status', $s)->count()])->all()
                + ['Unresolved items' => $assignments->whereIn('status', ['pending', 'contacted', 'unavailable'])->count()],
        ];
    }

    private function taskStatus(User $user, $from, $to): array
    {
        $tasks = $this->tasks($user, $from, $to)->with('booking:id,client_name', 'assignee:id,name')
            ->orderByRaw("CASE status WHEN 'ongoing' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")->orderBy('due_date')->get();

        return [
            'columns' => ['Due' => 'left', 'Task' => 'left', 'Booking' => 'left', 'Assigned To' => 'left', 'Priority' => 'left', 'Status' => 'left', 'Overdue' => 'left'],
            'rows' => $tasks->map(fn ($t) => [
                $t->due_date->format('M j, Y'), $t->title, $t->booking?->client_name ?? 'General', $t->assignee?->name,
                ucfirst($t->priority), ucfirst($t->status), $t->is_overdue && $t->status !== 'completed' ? 'OVERDUE' : '',
            ])->all(),
            'summary' => collect(Task::STATUSES)->mapWithKeys(fn ($s) => [ucfirst($s) => $tasks->where('status', $s)->count()])->all()
                + ['Overdue' => $tasks->where('is_overdue', true)->where('status', '!=', 'completed')->count()],
        ];
    }

    private function inventoryMovement(User $user, $from, $to): array
    {
        $movements = InventoryMovement::with('item:id,name,unit', 'booking:id,client_name', 'user:id,name')
            ->when($user->role !== 'admin', fn ($q) => $q->where(fn ($w) => $w->whereNull('booking_id')->orWhereIn('booking_id', Booking::visibleTo($user)->select('id'))))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->latest()->get();

        return [
            'columns' => ['Date' => 'left', 'Item' => 'left', 'Change' => 'right', 'Qty After' => 'right', 'Reason' => 'left', 'Booking' => 'left', 'By' => 'left'],
            'rows' => $movements->map(fn ($m) => [
                $m->created_at->format('M j, Y g:i A'), $m->item?->name, ($m->change > 0 ? '+' : '').$m->change.' '.$m->item?->unit,
                $m->quantity_after, $m->reason, $m->booking ? '#'.$m->booking->id.' '.$m->booking->client_name : '—', $m->user?->name ?? 'System',
            ])->all(),
            'summary' => [
                'Stock in (units)' => $movements->where('change', '>', 0)->sum('change'),
                'Stock out (units)' => abs($movements->where('change', '<', 0)->sum('change')),
                'Committed to bookings (units)' => abs($movements->whereNotNull('booking_id')->where('change', '<', 0)->sum('change')),
                'Low-stock items now' => InventoryItem::lowStock()->count(),
            ],
        ];
    }

    private function clientPaymentHistory(User $user, $from, $to): array
    {
        $bookings = $this->bookings($user, $from, $to)->where('status', '!=', 'cancelled')
            ->withSum('payments', 'amount')->withCount('payments')->withMax('payments', 'payment_date')
            ->orderBy('client_name')->get();

        $contract = $bookings->sum('total_amount');
        $paid = $bookings->sum('payments_sum_amount');

        return [
            'columns' => ['Client' => 'left', 'Event Date' => 'left', 'Contract' => 'right', 'Paid' => 'right', 'Balance' => 'right', 'Payments' => 'right', 'Last Payment' => 'left', 'Status' => 'left'],
            'rows' => $bookings->map(function ($b) {
                $paid = (float) $b->payments_sum_amount;

                return [
                    $b->client_name, $b->event_date->format('M j, Y'), $this->money($b->total_amount), $this->money($paid),
                    $this->money((float) $b->total_amount - $paid), $b->payments_count,
                    $b->payments_max_payment_date ? date('M j, Y', strtotime($b->payments_max_payment_date)) : '—',
                    $paid <= 0 ? 'Unpaid' : ($paid >= (float) $b->total_amount ? 'Paid' : 'Partial'),
                ];
            })->all(),
            'summary' => [
                'Total contract value' => $this->money($contract),
                'Total received' => $this->money($paid),
                'Total outstanding' => $this->money($contract - $paid),
                'Collection rate' => round($paid / max(1, $contract) * 100).'%',
            ],
        ];
    }

    private function money($amount): string
    {
        return '₱'.number_format((float) $amount, 2);
    }
}
