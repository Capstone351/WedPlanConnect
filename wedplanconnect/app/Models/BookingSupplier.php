<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['booking_id', 'supplier_id', 'status', 'order_sent_at', 'responded_at', 'response_note'])]
class BookingSupplier extends Pivot
{
    use SoftDeletes;

    public const STATUSES = ['pending', 'contacted', 'confirmed', 'unavailable'];

    protected $table = 'booking_suppliers';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'order_sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** True once the planner has sent the order and the supplier hasn't answered yet. */
    public function awaitingReply(): bool
    {
        return $this->status === 'contacted' && $this->order_sent_at !== null && $this->responded_at === null;
    }

    public function markOrderSent(): void
    {
        $this->update(['status' => 'contacted', 'order_sent_at' => now(), 'responded_at' => null, 'response_note' => null]);
    }

    public function recordReply(bool $accepted, ?string $note): void
    {
        $this->update(['status' => $accepted ? 'confirmed' : 'unavailable', 'responded_at' => now(), 'response_note' => $note]);
    }

    /**
     * Sends a previously sent order back to "pending" after its items change,
     * so the supplier reviews and accepts the updated order. Returns true when it was reopened.
     */
    public function reopenIfSent(): bool
    {
        if ($this->order_sent_at === null) {
            return false;
        }

        $this->update(['status' => 'pending', 'order_sent_at' => null, 'responded_at' => null, 'response_note' => null]);

        return true;
    }

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
