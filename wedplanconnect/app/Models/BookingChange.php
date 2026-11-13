<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in a booking's change history: who changed which detail, from what, to what, and why.
 */
#[Fillable(['booking_id', 'user_id', 'field', 'old_value', 'new_value', 'reason'])]
class BookingChange extends Model
{
    /** Details whose changes are recorded, with the label shown to people. */
    public const LABELS = [
        'event_date' => 'Wedding date',
        'venue' => 'Venue',
        'package' => 'Package',
        'total_amount' => 'Contract amount',
        'planner_id' => 'Planner',
        'status' => 'Status',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function label(): string
    {
        return self::LABELS[$this->field] ?? ucfirst(str_replace('_', ' ', $this->field));
    }

    /** Readable form of a stored value (dates, money, planner names). */
    public static function display(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field) {
            'event_date' => Carbon::parse($value)->format('F j, Y'),
            'total_amount' => '₱'.number_format((float) $value, 2),
            'planner_id' => User::withTrashed()->find($value)?->name ?? '—',
            'status' => ucfirst((string) $value),
            default => (string) $value,
        };
    }

    public function summary(): string
    {
        return $this->label().': '.self::display($this->field, $this->old_value).' → '.self::display($this->field, $this->new_value);
    }
}
