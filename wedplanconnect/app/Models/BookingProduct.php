<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'supplier_product_id', 'quantity', 'unit_price', 'notes'])]
class BookingProduct extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class, 'supplier_product_id')->withTrashed();
    }

    public function subtotal(): ?float
    {
        return $this->unit_price === null ? null : round((float) $this->unit_price * $this->quantity, 2);
    }
}
