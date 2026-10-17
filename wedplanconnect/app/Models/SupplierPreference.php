<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A couple's favorite supplier. Belongs to the couple's account so it can be saved
 * before a booking exists; planners see it on the couple's bookings.
 */
#[Fillable(['user_id', 'booking_id', 'supplier_id', 'notes'])]
class SupplierPreference extends Model
{
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }
}
