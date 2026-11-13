<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['booking_id', 'amount', 'payment_date', 'method', 'receipt_number', 'notes', 'recorded_by'])]
class Payment extends Model
{
    use HasFactory, SoftDeletes;

    public const METHODS = [
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'bank_transfer' => 'Bank Transfer',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    /** Staff member who recorded the payment. */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    /** Staff member who voided the payment, if it was voided. */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by')->withTrashed();
    }

    /** Newest payments first; same-day payments by the order they were recorded. */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('payment_date')->orderByDesc('id');
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
