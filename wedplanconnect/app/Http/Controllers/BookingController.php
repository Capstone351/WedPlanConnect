<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Support\Network;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * UC-03 Create and Edit Wedding Booking, plus confirmation → QR generation (UC-04).
 */
class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $base = Booking::visibleTo($user);

        $bookings = (clone $base)
            ->with('planner')
            ->withSum('payments', 'amount')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where(fn ($w) => $w->where('client_name', 'like', $q)->orWhere('venue', 'like', $q)->orWhere('package', 'like', $q));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('event_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('event_date', '<=', $request->date('to')))
            ->orderByRaw("CASE WHEN status IN ('pending','confirmed') THEN 0 ELSE 1 END")
            ->orderBy('event_date')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => (clone $base)->count(),
            'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'revenue' => (float) Payment::whereIn('booking_id', (clone $base)->select('id'))->sum('amount'),
        ];

        return view('bookings.index', compact('bookings', 'stats'));
    }

    public function create(): View
    {
        return view('bookings.form', $this->formData(new Booking(['status' => 'pending'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $booking = DB::transaction(function () use ($data, $request) {
            $this->guardConflict($data['event_date'], $data['venue']);

            $data['client_id'] = $data['client_id'] ?? $this->createClientAccount($request)->id;
            $data['planner_id'] = $request->user()->role === 'planner' ? $request->user()->id : $data['planner_id'];
            $data['status'] = 'pending';

            return Booking::create($data);
        });

        return redirect()->route('bookings.show', $booking)->with('status', 'Booking created. No scheduling conflicts were found.');
    }

    public function show(Booking $booking): View
    {
        $this->authorizeBooking($booking);

        $booking->load([
            'planner', 'client',
            'bookingSuppliers' => fn ($q) => $q->with('supplier')->latest(),
            'preferences.supplier',
            'tasks' => fn ($q) => $q->with('assignee')->orderBy('status')->orderBy('due_date'),
            'payments' => fn ($q) => $q->orderByDesc('payment_date'),
            'bookingProducts' => fn ($q) => $q->with('product.supplier')->latest(),
        ]);

        $assignedIds = $booking->bookingSuppliers->pluck('supplier_id');

        return view('bookings.show', [
            'booking' => $booking,
            'availableSuppliers' => Supplier::whereNotIn('id', $assignedIds)->orderBy('category')->orderBy('name')->get(),
            // Products the planner can add to the set-up, grouped by supplier in the view.
            'selectableProducts' => SupplierProduct::selectable()->with('supplier:id,name,category')
                ->whereNotIn('id', $booking->bookingProducts->pluck('supplier_product_id'))
                ->get()->sortBy(fn ($p) => [$p->supplier->category, $p->supplier->name, $p->name])->values(),
            'planners' => User::whereIn('role', ['planner', 'admin'])->orderBy('name')->get(),
            'qrSvg' => $booking->qr_token ? QrCode::format('svg')->size(180)->margin(1)->generate($booking->statusUrl()) : null,
        ]);
    }

    public function edit(Booking $booking): View
    {
        $this->authorizeBooking($booking);
        abort_if(in_array($booking->status, ['cancelled', 'completed']), 403, 'Cancelled or completed bookings can no longer be edited.');

        return view('bookings.form', $this->formData($booking));
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);
        abort_if(in_array($booking->status, ['cancelled', 'completed']), 403, 'Cancelled or completed bookings can no longer be edited.');

        $data = $this->validated($request, $booking);

        DB::transaction(function () use ($booking, $data, $request) {
            $this->guardConflict($data['event_date'], $data['venue'], $booking->id);

            $data['client_id'] = $data['client_id'] ?? $this->createClientAccount($request)->id;

            if ($request->user()->role !== 'admin') {
                unset($data['planner_id']);
            }

            $booking->update($data);
        });

        return redirect()->route('bookings.show', $booking)->with('status', 'Booking updated.');
    }

    public function confirm(Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);

        if ($booking->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending bookings can be confirmed.']);
        }

        $booking->update(['status' => 'confirmed']);
        $booking->ensureQrToken();

        return redirect()->route('bookings.qr', $booking)->with('status', 'Booking confirmed. The client QR code has been generated.');
    }

    public function complete(Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);

        if ($booking->status !== 'confirmed') {
            return back()->withErrors(['status' => 'Only confirmed bookings can be marked as completed.']);
        }

        $booking->update(['status' => 'completed']);

        return back()->with('status', 'Booking marked as completed.');
    }

    /** Cancels the booking and invalidates its QR token so the status page can no longer be opened. */
    public function cancel(Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);

        if (in_array($booking->status, ['cancelled', 'completed'])) {
            return back()->withErrors(['status' => 'This booking can no longer be cancelled.']);
        }

        $booking->update(['status' => 'cancelled']);
        $booking->invalidateQrAccess();

        return redirect()->route('bookings.show', $booking)->with('status', 'Booking cancelled. Its QR code has been invalidated.');
    }

    public function qr(Booking $booking): View|RedirectResponse
    {
        $this->authorizeBooking($booking);

        if (! $booking->qr_token) {
            return redirect()->route('bookings.show', $booking)->withErrors(['qr' => 'A QR code is generated only after the booking is confirmed.']);
        }

        return view('bookings.qr', [
            'booking' => $booking->loadMissing('client'),
            'statusUrl' => $booking->statusUrl(),
            'qrSvg' => QrCode::format('svg')->size(280)->margin(1)->errorCorrection('M')->generate($booking->statusUrl()),
            'networkWarning' => $this->qrNetworkWarning(),
        ]);
    }

    public function qrDownload(Booking $booking): Response
    {
        $this->authorizeBooking($booking);
        abort_unless($booking->qr_token, 404);

        $svg = QrCode::format('svg')->size(600)->margin(2)->errorCorrection('M')->generate($booking->statusUrl());

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="wedding-status-qr-'.$booking->id.'.svg"',
        ]);
    }

    /**
     * Warns staff when phones won't be able to open the QR link because this PC isn't on a network.
     * (Changes of Wi-Fi address are handled automatically by Network::qrBaseUrl().)
     */
    private function qrNetworkWarning(): ?string
    {
        $host = parse_url(Network::qrBaseUrl(), PHP_URL_HOST);

        if (Network::isLoopback($host)) {
            return 'This computer is not connected to Wi-Fi, so the QR link only works on this computer. Connect to Wi-Fi, then reload this page.';
        }

        return null;
    }

    private function formData(Booking $booking): array
    {
        return [
            'booking' => $booking,
            'clients' => User::where('role', 'client')->orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'planners' => User::where('role', 'planner')->orderBy('name')->get(['id', 'name']),
            'packages' => ['Classic Elegance', 'Garden Romance', 'Rustic Charm', 'Grand Ballroom', 'Beach Wedding', 'Intimate Ceremony', 'Custom Package'],
        ];
    }

    private function validated(Request $request, ?Booking $booking = null): array
    {
        $dateChanged = ! $booking || $request->input('event_date') !== $booking->event_date?->toDateString();

        return $request->validate([
            'client_id' => ['nullable', 'required_without:new_client_email', Rule::exists('users', 'id')->where('role', 'client')],
            'new_client_name' => ['nullable', 'required_without:client_id', 'string', 'max:150'],
            'new_client_email' => ['nullable', 'required_without:client_id', 'email', 'max:150', 'unique:users,email'],
            'planner_id' => [$request->user()->role === 'admin' ? 'required' : 'nullable', Rule::exists('users', 'id')->where('role', 'planner')],
            'client_name' => ['required', 'string', 'max:150'],
            'contact_number' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'event_date' => array_filter(['required', 'date', $dateChanged ? 'after_or_equal:today' : null]),
            'venue' => ['required', 'string', 'max:200'],
            'package' => ['required', 'string', 'max:100'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ], [
            'event_date.after_or_equal' => 'The event date cannot be in the past.',
            'contact_number.regex' => 'Enter a valid contact number, e.g. +639171234567.',
        ]) + ['client_id' => null];
    }

    /** Real-time double-booking detection (E1 of UC-03). */
    private function guardConflict(string $eventDate, string $venue, ?int $ignoreId = null): void
    {
        $conflict = Booking::findConflict($eventDate, $venue, $ignoreId);

        if ($conflict) {
            $where = config('wedplan.conflict_scope') === 'date_venue' ? " at {$conflict->venue}" : '';

            throw ValidationException::withMessages([
                'event_date' => "Double-booking detected: Booking #{$conflict->id} ({$conflict->client_name}) is already scheduled on "
                    .$conflict->event_date->format('F j, Y').$where.'.',
            ]);
        }
    }

    /** Creates a couple-client account inline and emails them a link to set their password. */
    private function createClientAccount(Request $request): User
    {
        $client = User::create([
            'role' => 'client',
            'name' => $request->input('new_client_name'),
            'email' => $request->input('new_client_email'),
            'phone' => $request->input('contact_number'),
            'password' => Str::random(40),
        ]);

        Password::sendResetLink(['email' => $client->email]);

        return $client;
    }
}
