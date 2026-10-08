<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'name', 'category', 'phone', 'email', 'description', 'starting_price', 'availability'])]
class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = [
        'Florist', 'Photographer', 'Videographer', 'Caterer', 'Venue',
        'Lights & Sounds', 'Hair & Makeup', 'Cake', 'Gowns & Suits', 'Host / Emcee', 'Band / Musicians',
    ];

    protected function casts(): array
    {
        return ['starting_price' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
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
