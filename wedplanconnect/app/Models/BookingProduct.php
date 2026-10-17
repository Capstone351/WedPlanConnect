<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'supplier_product_id', 'quantity', 'due_date', 'unit_price', 'notes'])]
class BookingProduct extends Model
{
    /** Deadlines this close (in days) are flagged so the vendor starts preparing. */
    public const DUE_SOON_DAYS = 7;

    protected static function booted(): void
    {
        // Without a planner-set deadline, the vendor has it ready the day before the wedding.
        static::creating(function (BookingProduct $item) {
            if (! $item->due_date && $booking = Booking::find($item->booking_id)) {
                $item->due_date = static::suggestedDueDate($booking);
            }
        });
    }

    /** The day before the wedding, but never in the past. */
    public static function suggestedDueDate(Booking $booking): CarbonInterface
    {
        return $booking->event_date->copy()->subDay()->max(today())->min($booking->event_date);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'due_date' => 'date',
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

    /** Days until the product must be ready; negative once the deadline has passed. */
    public function daysUntilDue(): ?int
    {
        return $this->due_date ? (int) today()->diffInDays($this->due_date, false) : null;
    }

    /** Human label and badge colour for the deadline, e.g. ["Due in 3 days", "amber"]. */
    public function dueStatus(): array
    {
        $days = $this->daysUntilDue();

        return match (true) {
            $days === null => ['No deadline', 'gray'],
            $days < 0 => ['Overdue by '.abs($days).' '.str('day')->plural(abs($days)), 'red'],
            $days === 0 => ['Due today', 'red'],
            $days <= self::DUE_SOON_DAYS => ['Due in '.$days.' '.str('day')->plural($days), 'amber'],
            default => ['Due in '.$days.' days', 'green'],
        };
    }
}
