<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * UC-09 Generate Reports. Admin: all report types. Wedding Planner: operational reports for own bookings.
 */
class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $bookings = Booking::visibleTo($user);

        // Revenue trend: payments received per month over the last 6 months.
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $payments = Payment::whereIn('booking_id', (clone $bookings)->select('id'))
            ->whereDate('payment_date', '>=', $months->first())
            ->get(['amount', 'payment_date']);
        $trend = $months->map(fn ($m) => [
            'label' => $m->format('M'),
            'value' => (float) $payments->filter(fn ($p) => $p->payment_date->isSameMonth($m))->sum('amount'),
        ]);

        return view('reports.index', [
            'types' => ReportService::allowedFor($user),
            'trend' => $trend,
            'byStatus' => (clone $bookings)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'metrics' => [
                'bookings' => (clone $bookings)->count(),
                'revenue' => (float) Payment::whereIn('booking_id', (clone $bookings)->select('id'))->sum('amount'),
                'thisMonth' => (float) $payments->filter(fn ($p) => $p->payment_date->isSameMonth(now()))->sum('amount'),
                'activeClients' => (clone $bookings)->upcoming()->distinct()->count('client_id'),
            ],
        ]);
    }

    public function show(Request $request, string $type): View
    {
        return view('reports.show', ['report' => $this->build($request, $type), 'filters' => $request->only('from', 'to')]);
    }

    public function pdf(Request $request, string $type): Response
    {
        $report = $this->build($request, $type);

        return Pdf::loadView('pdf.report', ['report' => $report, 'filters' => $request->only('from', 'to'), 'generatedBy' => $request->user()])
            ->setPaper('a4', count($report['columns']) > 6 ? 'landscape' : 'portrait')
            ->download($type.'-'.now()->format('Ymd-His').'.pdf');
    }

    private function build(Request $request, string $type): array
    {
        abort_unless(array_key_exists($type, ReportService::TYPES), 404);
        abort_unless(array_key_exists($type, ReportService::allowedFor($request->user())), 403, 'This report is available to administrators only.');

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return $this->reports->build(
            $type,
            $request->user(),
            $request->filled('from') ? Carbon::parse($request->input('from')) : null,
            $request->filled('to') ? Carbon::parse($request->input('to')) : null,
        );
    }
}
