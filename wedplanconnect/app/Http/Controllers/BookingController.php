<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingChange;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Notifications\BookingChanged;
use App\Notifications\BookingConfirmed;
use App\Support\Network;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
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

    public function create(Request $request): View
    {
        $booking = new Booking(['status' => 'pending']);

        // "Create booking" from a couple who chose a planner: pre-fill the couple and their chosen planner.
        if ($client = User::where('role', 'client')->find($request->integer('client_id'))) {
            $booking->fill([
                'client_id' => $client->id,
                'client_name' => $client->coupleNames() ?? $client->name,
                'contact_number' => $client->phone,
                'planner_id' => $client->preferred_planner_id,
                'event_date' => $client->wedding_date?->isFuture() ? $client->wedding_date : null,
                'venue' => $client->wedding_venue,
                'package' => $client->wedding_package,
                'total_amount' => Booking::packagePrice($client->wedding_package),
            ]);
        }

        return view('bookings.form', $this->formData($booking));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $data['planner_id'] = $request->user()->role === 'planner' ? $request->user()->id : $data['planner_id'];

        $booking = Booking::withDateLock($data['event_date'], fn () => DB::transaction(function () use ($data, $request) {
            $this->guardConflict($data['event_date'], $data['venue'], null, $data['planner_id']);

            $data['client_id'] = $data['client_id'] ?? $this->createClientAccount($request)->id;
            $data['status'] = 'pending';

            return Booking::create($data);
        }));

        return redirect()->route('bookings.show', $booking)->with('status', 'Booking created. No scheduling conflicts were found.');
    }

    public function show(Booking $booking): View
    {
        $this->authorizeBooking($booking);

        $booking->load([
            'planner', 'client',
            'bookingSuppliers' => fn ($q) => $q->with('supplier.user')->latest(),
            'preferences.supplier',
            'tasks' => fn ($q) => $q->with('assignee')->orderBy('status')->orderBy('due_date'),
            'payments' => fn ($q) => $q->latestFirst(),
            'bookingProducts' => fn ($q) => $q->with('product.supplier')->latest(),
            'changes.user',
        ]);

        $assignedIds = $booking->bookingSuppliers->pluck('supplier_id');
        $budget = $booking->client?->budget()->with('allocations')->first();

        return view('bookings.show', [
            'booking' => $booking,
            // The couple's budget, so suppliers and products can be picked to fit it.
            'budget' => $budget,
            'budgetByCategory' => $budget?->byCategory($booking->bookingProducts) ?? collect(),
            'availableSuppliers' => Supplier::whereNotIn('id', $assignedIds)->orderBy('category')->orderBy('name')->get(),
            // Products the planner can add to the set-up, grouped by supplier in the view.
            'selectableProducts' => SupplierProduct::selectable()->withReserved()->with('supplier:id,name,category')
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
        $request->validate(['change_reason' => ['nullable', 'string', 'max:500']]);
        $reason = trim((string) $request->input('change_reason')) ?: null;

        if ($request->user()->role !== 'admin') {
            unset($data['planner_id']);
        }

        // The contract can't drop below what the couple has already paid.
        $paid = $booking->totalPaid();
        if ((float) $data['total_amount'] + 0.001 < $paid) {
            throw ValidationException::withMessages(['total_amount' => 'The contract amount cannot be lower than the ₱'.number_format($paid, 2).' already paid.']);
        }

        $tracked = ['event_date', 'venue', 'package', 'total_amount', 'planner_id'];
        $before = $booking->only($tracked);
        $changing = array_intersect(array_keys((clone $booking)->fill(Arr::only($data, $tracked))->getDirty()), $tracked);

        // Once confirmed, the couple has agreed to the details: changing them needs a reason they can see.
        if ($changing && $booking->status === 'confirmed' && ! $reason) {
            throw ValidationException::withMessages(['change_reason' => 'This booking is confirmed. Please give a reason for changing the '
                .Arr::join(array_map(fn ($f) => strtolower(BookingChange::LABELS[$f]), $changing), ', ', ' and ').'. The couple will see it.']);
        }

        $changes = Booking::withDateLock($data['event_date'], fn () => DB::transaction(function () use ($booking, $data, $request, $before, $reason) {
            $this->guardConflict($data['event_date'], $data['venue'], $booking->id, $data['planner_id'] ?? $booking->planner_id);

            $data['client_id'] = $data['client_id'] ?? $this->createClientAccount($request)->id;

            $booking->update($data);

            return $booking->recordChanges($before, $reason, $request->user());
        }));

        if ($changes->isNotEmpty() && $booking->client) {
            // Keep the couple's own wedding date in step with their booking.
            if ($changes->contains('field', 'event_date')) {
                $booking->client->forceFill(['wedding_date' => $booking->event_date])->save();
            }

            $booking->client->notify(new BookingChanged($booking, $changes, $reason, $request->user()->name));
        }

        return redirect()->route('bookings.show', $booking)->with('status', $changes->isNotEmpty() ? 'Booking updated. The couple has been notified of the changes.' : 'Booking updated.');
    }

    public function confirm(Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);

        if ($booking->status !== 'pending') {
            return back()->withErrors(['status' => 'Only pending bookings can be confirmed.']);
        }

        // Couples can send their booking before these are agreed; the planner fills them in first.
        if ($missing = $booking->missingBeforeConfirm()) {
            return back()->withErrors(['status' => 'Set the '.Arr::join($missing, ', ', ' and ').' (Edit) before confirming this booking.']);
        }

        $booking->update(['status' => 'confirmed']);
        $booking->ensureQrToken();
        $booking->client?->notify(new BookingConfirmed($booking));

        return redirect()->route('bookings.qr', $booking)->with('status', 'Booking confirmed. The client QR code has been generated.');
    }

    public function complete(Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);

        if ($booking->status !== 'confirmed') {
            return back()->withErrors(['status' => 'Only confirmed bookings can be marked as completed.']);
        }

        if ($booking->event_date->isFuture()) {
            return back()->withErrors(['status' => 'A wedding can only be marked as completed on or after its date ('.$booking->event_date->format('F j, Y').').']);
        }

        $before = ['status' => $booking->status];
        $booking->update(['status' => 'completed']);
        $booking->recordChanges($before, null, auth()->user());

        $balance = $booking->balance();

        return back()->with('status', 'Booking marked as completed.'.($balance > 0 ? ' Note: ₱'.number_format($balance, 2).' of the contract is still unpaid.' : ''));
    }

    /**
     * Cancels (or declines) the booking with a reason the couple sees. Frees the date,
     * invalidates the QR code, and archives the wedding's open tasks.
     */
    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);

        if (in_array($booking->status, ['cancelled', 'completed'])) {
            return back()->withErrors(['status' => 'This booking can no longer be cancelled.']);
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [
            'reason.required' => 'Please give a reason for cancelling. The couple will see it.',
        ]);

        $booking->cancelWithReason(trim($data['reason']), $request->user());

        return redirect()->route('bookings.show', $booking)->with('status', 'Booking cancelled. The couple has been notified, its QR code invalidated, and its open tasks archived.');
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
            'clients' => User::where('role', 'client')->with('preferredPlanner:id,name')->orderBy('name')->get(['id', 'name', 'email', 'phone', 'preferred_planner_id', 'wedding_date', 'wedding_date_alt', 'wedding_venue']),
            'planners' => User::where('role', 'planner')->orderBy('name')->get(['id', 'name']),
            'packages' => [...array_keys(Booking::packages()), 'Custom Package'],
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
            'venue' => ['nullable', 'string', 'max:200'],
            'package' => ['nullable', 'string', 'max:100'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ], [
            'event_date.after_or_equal' => 'The event date cannot be in the past.',
            'contact_number.regex' => 'Enter a valid contact number, e.g. +639171234567.',
        ]) + ['client_id' => null];
    }

    /** Real-time double-booking detection (E1 of UC-03). */
    private function guardConflict(string $eventDate, ?string $venue, ?int $ignoreId = null, ?int $plannerId = null): void
    {
        // A planner handles one wedding per day, whether or not the venue is decided yet.
        if ($plannerId && $busy = Booking::plannerBusyOn($plannerId, $eventDate, $ignoreId)) {
            throw ValidationException::withMessages([
                'event_date' => "Double-booking detected: this planner already has Booking #{$busy->id} ({$busy->client_name}) on "
                    .$busy->event_date->format('F j, Y').'.',
            ]);
        }

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
