<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['booking_id', 'supplier_id', 'status'])]
class BookingSupplier extends Pivot
{
    use SoftDeletes;

    public const STATUSES = ['pending', 'contacted', 'confirmed', 'unavailable'];

    protected $table = 'booking_suppliers';

    public $incrementing = true;

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public static function badge(string $status): string
    {
        return match ($status) {
            'confirmed' => 'green',
            'contacted' => 'blue',
            'unavailable' => 'red',
            default => 'amber',
        };
    }
}
