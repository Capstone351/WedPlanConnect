<?php

namespace App\Models;

use App\Support\Network;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['planner_id', 'client_id', 'client_name', 'contact_number', 'event_date', 'venue', 'package', 'total_amount', 'status'])]
#[Hidden(['qr_token', 'otp_code', 'otp_expires_at', 'otp_attempts'])]
class Booking extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'confirmed', 'completed', 'cancelled'];

    /** Statuses that occupy a date/venue slot for double-booking detection. */
    public const ACTIVE_STATUSES = ['pending', 'confirmed'];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'total_amount' => 'decimal:2',
            'otp_expires_at' => 'datetime',
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

    public function preferences(): HasMany
    {
        return $this->hasMany(SupplierPreference::class);
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
     * Finds an active booking that conflicts with the given slot.
     * Scope comes from config('wedplan.conflict_scope'): 'date_venue' (default) or 'date'.
     */
    public static function findConflict(string $eventDate, string $venue, ?int $ignoreId = null): ?self
    {
        $query = self::active()->whereDate('event_date', $eventDate);

        if (config('wedplan.conflict_scope') === 'date_venue') {
            $query->whereRaw('LOWER(TRIM(venue)) = ?', [Str::lower(trim($venue))]);
        }

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->first();
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
