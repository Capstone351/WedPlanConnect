<?php

namespace App\Models;

use App\Notifications\BookingCancelled;
use App\Support\Network;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

#[Fillable(['planner_id', 'client_id', 'client_name', 'contact_number', 'event_date', 'venue', 'package', 'total_amount', 'status'])]
#[Hidden(['qr_token', 'otp_code', 'otp_expires_at', 'otp_attempts'])]
class Booking extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'confirmed', 'completed', 'cancelled'];

    /** Statuses that occupy a date/venue slot for double-booking detection. */
    public const ACTIVE_STATUSES = ['pending', 'confirmed'];

    /** Statuses whose payments can be recorded: only after the planner confirms the booking. */
    public const PAYABLE_STATUSES = ['confirmed', 'completed'];

    /**
     * The wedding packages couples choose from, keyed by name, most expensive first.
     *
     * @return array<string, array{price: int, guests: int, cake_layers: int, coordinators: int, freebies: list<string>}>
     */
    public static function packages(): array
    {
        return collect(config('wedplan.packages'))->sortByDesc('price')->all();
    }

    /** Price of a wedding package, or null for anything else (e.g. a custom package). */
    public static function packagePrice(?string $name): ?float
    {
        $price = self::packages()[$name]['price'] ?? null;

        return $price === null ? null : (float) $price;
    }

    /**
     * Preparation tasks a package brings: its inclusions, coordinators, and freebies, each worded as an action.
     *
     * @return array<string, array{inclusion: string, days: int, priority: string, details: string}> task title => task details
     */
    public static function packageTasks(?string $name): array
    {
        $package = self::packages()[$name] ?? null;

        if ($package === null) {
            return [];
        }

        $tasks = [];

        foreach (config('wedplan.package_inclusions') as $inclusion => $spec) {
            $tasks[$spec['task']] = ['inclusion' => $inclusion, 'days' => $spec['days'], 'priority' => $spec['priority'], 'details' => $spec['details']];
        }

        $coordinators = config('wedplan.package_coordinators_task');
        $tasks[str_replace(':count', $package['coordinators'], $coordinators['task'])] = [
            'inclusion' => $package['coordinators'].' On-the-Day Coordinators', 'days' => $coordinators['days'], 'priority' => $coordinators['priority'], 'details' => $coordinators['details'],
        ];

        foreach ($package['freebies'] as $freebie) {
            $spec = config('wedplan.package_freebies')[$freebie] ?? ['task' => "Prepare the freebie: {$freebie}", 'details' => 'Free with the package. Have it ready before the wedding.'];
            $tasks[$spec['task']] = ['inclusion' => "Freebie: {$freebie}", 'days' => config('wedplan.package_freebies_days'), 'priority' => 'low', 'details' => $spec['details']];
        }

        return $tasks;
    }

    /** Task description: which inclusion it comes from, then what has to be done. */
    public static function packageTaskDescription(string $package, array $spec): string
    {
        return "{$package} inclusion: {$spec['inclusion']}. {$spec['details']}";
    }

    /** Every task title any package can create, so package tasks can be told apart from the planner's own. */
    public static function allPackageTaskTitles(): array
    {
        return collect(self::packages())->keys()
            ->flatMap(fn (string $name) => array_keys(self::packageTasks($name)))
            ->unique()->values()->all();
    }

    protected static function booted(): void
    {
        // A new request holds the planner's date for a limited time until the planner answers it.
        static::creating(function (Booking $booking) {
            if ($booking->status === 'pending' && ! $booking->hold_expires_at) {
                $booking->hold_expires_at = now()->addDays(config('wedplan.pending_hold_days'));
            }
        });

        static::saved(function (Booking $booking) {
            // A wedding's open tasks always belong to its current planner.
            if ($booking->wasChanged('planner_id') && $booking->planner_id) {
                $booking->tasks()->open()->update(['assigned_to' => $booking->planner_id]);
            }

            // The preparation list starts once the planner accepts the wedding, not when a couple only asks.
            if ($booking->status === 'confirmed' && ($booking->wasRecentlyCreated || $booking->wasChanged(['status', 'package', 'event_date', 'planner_id']))) {
                $booking->syncPackageTasks();
            }
        });
    }

    /**
     * Keep the preparation list in step with the package: add a task for each inclusion that's missing,
     * remove untouched package tasks the package no longer has, and move untouched due dates with the wedding date.
     */
    public function syncPackageTasks(): void
    {
        if (! $this->planner_id || ! $this->event_date) {
            return;
        }

        $wanted = self::packageTasks($this->package);
        $existing = $this->tasks()->whereIn('title', self::allPackageTaskTitles())->get()->keyBy('title');

        foreach ($existing as $title => $task) {
            if ($task->status !== 'pending') {
                continue;
            }

            if (! array_key_exists($title, $wanted)) {
                $task->delete();
            } else {
                $task->update([
                    'description' => self::packageTaskDescription($this->package, $wanted[$title]),
                    'due_date' => $this->packageTaskDueDate($wanted[$title]['days']),
                    'assigned_to' => $this->planner_id,
                    'priority' => $wanted[$title]['priority'],
                ]);
            }
        }

        foreach ($wanted as $title => $spec) {
            if ($existing->has($title)) {
                continue;
            }

            $this->tasks()->create([
                'assigned_to' => $this->planner_id,
                'title' => $title,
                'description' => self::packageTaskDescription($this->package, $spec),
                'due_date' => $this->packageTaskDueDate($spec['days']),
                'priority' => $spec['priority'],
                'status' => 'pending',
            ]);
        }
    }

    /** Due date a set number of days before the wedding, but never in the past or after the wedding. */
    private function packageTaskDueDate(int $leadDays): CarbonInterface
    {
        $due = $this->event_date->copy()->subDays($leadDays);

        return $due->lt(today()) ? today()->min($this->event_date) : $due;
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'total_amount' => 'decimal:2',
            'otp_expires_at' => 'datetime',
            'hold_expires_at' => 'datetime',
        ];
    }

    public function planner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'planner_id')->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id')->withTrashed();
    }

    public function bookingSuppliers(): HasMany
    {
        return $this->hasMany(BookingSupplier::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'booking_suppliers')
            ->using(BookingSupplier::class)
            ->withPivot('id', 'status')
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    /** The couple's favorite suppliers (saved on their account). */
    public function preferences(): HasMany
    {
        return $this->hasMany(SupplierPreference::class, 'user_id', 'client_id');
    }

    public function portfolioEntry(): HasOne
    {
        return $this->hasOne(PortfolioEntry::class);
    }

    /** Vendor products the planner selected for this wedding's set-up. */
    public function bookingProducts(): HasMany
    {
        return $this->hasMany(BookingProduct::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Payments that were voided; kept so the record shows what was removed, by whom, and why. */
    public function voidedPayments(): HasMany
    {
        return $this->hasMany(Payment::class)->onlyTrashed();
    }

    /** Who changed which detail and why, newest first. */
    public function changes(): HasMany
    {
        return $this->hasMany(BookingChange::class)->latest('id');
    }

    /**
     * Records every tracked detail that differs from $before, with who changed it and why.
     *
     * @param  array<string, mixed>  $before  attribute values before the change
     * @return Collection<int, BookingChange>
     */
    public function recordChanges(array $before, ?string $reason, ?User $by): Collection
    {
        return collect(array_keys(BookingChange::LABELS))
            ->filter(fn (string $field) => array_key_exists($field, $before)
                && self::comparable($field, $before[$field]) !== self::comparable($field, $this->getAttribute($field)))
            ->map(fn (string $field) => $this->changes()->create([
                'user_id' => $by?->id,
                'field' => $field,
                'old_value' => self::comparable($field, $before[$field]),
                'new_value' => self::comparable($field, $this->getAttribute($field)),
                'reason' => $reason,
            ]))
            ->values()
            ->toBase();
    }

    /** Normalizes a value so "100000" and 100000.00, or a date and its string, compare as equal. */
    private static function comparable(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'event_date' => Carbon::parse($value)->toDateString(),
            'total_amount' => number_format((float) $value, 2, '.', ''),
            default => (string) $value,
        };
    }

    /** When an unconfirmed booking stops holding the planner's date. */
    public function holdExpiresAt(): ?CarbonInterface
    {
        return $this->status === 'pending' ? $this->hold_expires_at : null;
    }

    /**
     * Cancels the booking: frees the date, invalidates the QR code, archives its open tasks,
     * records the reason, and tells the couple.
     */
    public function cancelWithReason(string $reason, ?User $by): void
    {
        $before = ['status' => $this->status];

        $this->update(['status' => 'cancelled']);
        $this->invalidateQrAccess();
        $this->tasks()->open()->delete();
        $this->recordChanges($before, $reason, $by);

        $this->client?->notify(new BookingCancelled($this, $reason));
    }

    /**
     * Releases dates held by bookings the planner never confirmed in time, so throwaway or
     * forgotten requests can't block a planner forever. Runs at most once per request.
     */
    public static function releaseExpiredHolds(): void
    {
        once(function () {
            self::where('status', 'pending')
                ->where('hold_expires_at', '<', now())
                ->each(fn (Booking $booking) => $booking->cancelWithReason(
                    'The planner did not confirm this booking within '.config('wedplan.pending_hold_days').' days, so the date was released.',
                    null,
                ));
        });
    }

    /**
     * Runs $callback while holding a lock on the wedding date, so two bookings sent at the same moment
     * are checked for conflicts one after the other instead of both slipping through.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withDateLock(string $eventDate, callable $callback): mixed
    {
        $date = Carbon::parse($eventDate)->toDateString();

        return Cache::lock("booking-date:{$date}", 10)->block(5, $callback);
    }

    /** The planner's other active wedding on that date, if any (a planner handles one wedding per day). */
    public static function plannerBusyOn(int $plannerId, string $eventDate, ?int $ignoreId = null): ?self
    {
        self::releaseExpiredHolds();

        return self::active()->where('planner_id', $plannerId)->whereDate('event_date', $eventDate)
            ->when($ignoreId, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->first();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /** Active bookings whose wedding date is today or later. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->active()->whereDate('event_date', '>=', today());
    }

    /** Active bookings whose wedding date has passed but were never marked completed or cancelled. */
    public function scopeAwaitingClosure(Builder $query): Builder
    {
        return $query->active()->whereDate('event_date', '<', today());
    }

    /** Bookings visible to a user: admins see all, planners their own, clients theirs. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'admin' => $query,
            'planner' => $query->where('planner_id', $user->id),
            'client' => $query->where('client_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * IDs of planners who already have an active wedding on the given date.
     *
     * @return Collection<int, int>
     */
    public static function plannerIdsBookedOn(CarbonInterface $date): Collection
    {
        self::releaseExpiredHolds();

        return self::active()->whereDate('event_date', $date->toDateString())->distinct()->pluck('planner_id');
    }

    /**
     * Finds an active booking that conflicts with the given slot.
     * Scope comes from config('wedplan.conflict_scope'): 'date_venue' (default) or 'date'.
     */
    public static function findConflict(string $eventDate, ?string $venue, ?int $ignoreId = null): ?self
    {
        self::releaseExpiredHolds();

        $query = self::active()->whereDate('event_date', $eventDate);

        if (config('wedplan.conflict_scope') === 'date_venue') {
            // No venue decided yet, so it can't clash with another wedding's venue.
            if (blank($venue)) {
                return null;
            }

            $query->whereRaw('LOWER(TRIM(venue)) = ?', [Str::lower(trim($venue))]);
        }

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->first();
    }

    public function venueLabel(): string
    {
        return $this->venue ?? 'Venue to be discussed';
    }

    public function packageLabel(): string
    {
        return $this->package ?? 'Package to be discussed';
    }

    /**
     * What still has to be agreed before the booking can be confirmed,
     * e.g. for a couple who sent their booking before meeting the planner.
     *
     * @return array<int, string>
     */
    public function missingBeforeConfirm(): array
    {
        return array_keys(array_filter([
            'venue' => blank($this->venue),
            'package' => blank($this->package),
            'price' => (float) $this->total_amount <= 0,
        ]));
    }

    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balance(): float
    {
        return round((float) $this->total_amount - $this->totalPaid(), 2);
    }

    public function paymentStatus(): string
    {
        $paid = $this->totalPaid();

        if ($paid <= 0) {
            return 'Unpaid';
        }

        return $paid >= (float) $this->total_amount ? 'Paid' : 'Partial';
    }

    /**
     * Preparation progress: share of completed tasks and confirmed suppliers.
     */
    public function progress(): int
    {
        $tasks = $this->tasks()->count();
        $doneTasks = $this->tasks()->where('status', 'completed')->count();
        $suppliers = $this->bookingSuppliers()->count();
        $confirmed = $this->bookingSuppliers()->where('status', 'confirmed')->count();

        $total = $tasks + $suppliers;

        return $total === 0 ? 0 : (int) round(($doneTasks + $confirmed) / $total * 100);
    }

    public function daysToGo(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->event_date, false);
    }

    /** Issues the 64-character hexadecimal QR token (UC-04). Reuses an existing token. */
    public function ensureQrToken(): string
    {
        if (! $this->qr_token) {
            $this->qr_token = bin2hex(random_bytes(32));
            $this->save();
        }

        return $this->qr_token;
    }

    /**
     * Address encoded in the QR code. Uses this PC's current Wi-Fi address during local use
     * (or APP_URL on a real domain), so printed codes open on couples' phones.
     */
    public function statusUrl(): ?string
    {
        return $this->qr_token
            ? Network::qrBaseUrl().route('qr.show', $this->qr_token, false)
            : null;
    }

    public function invalidateQrAccess(): void
    {
        $this->forceFill([
            'qr_token' => null,
            'otp_code' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
        ])->save();
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'confirmed' => 'green',
            'completed' => 'blue',
            'cancelled' => 'gray',
            default => 'amber',
        };
    }
}
