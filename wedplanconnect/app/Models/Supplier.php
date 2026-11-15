<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

#[Fillable(['user_id', 'name', 'category', 'phone', 'email', 'description', 'starting_price', 'availability'])]
class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = [
        'Coordination', 'Hair & Make-up Artists', 'Photo & Video', 'Lights & Sounds',
        'Event Styling', 'Cakes', 'Entourage Dress(es)',
    ];

    protected function casts(): array
    {
        return ['starting_price' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** True when the supplier can be reached: through their vendor login, or by email. */
    public function isReachable(): bool
    {
        return ($this->user && ! $this->user->trashed()) || filled($this->email);
    }

    /**
     * Sends a notification to the supplier's vendor login (in-app and email), or by email only
     * when they have no login. Returns false when they can't be reached at all.
     */
    public function sendNotification(Notification $notification): bool
    {
        if ($this->user && ! $this->user->trashed()) {
            $this->user->notify($notification);
        } elseif (filled($this->email)) {
            NotificationFacade::route('mail', $this->email)->notify($notification);
        } else {
            return false;
        }

        return true;
    }

    public function bookingSuppliers(): HasMany
    {
        return $this->hasMany(BookingSupplier::class);
    }

    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_suppliers')
            ->using(BookingSupplier::class)
            ->withPivot('id', 'status')
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(SupplierPreference::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }
}
