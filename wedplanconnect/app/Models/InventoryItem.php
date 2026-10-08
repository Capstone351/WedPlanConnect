<?php

namespace App\Models;

use App\Observers\InventoryItemObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'category', 'quantity', 'unit', 'min_threshold', 'unit_cost'])]
#[ObservedBy(InventoryItemObserver::class)]
class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = ['Florals', 'Fabrics & Drapes', 'Lighting', 'Tableware', 'Furniture', 'Centerpieces', 'Arches & Backdrops', 'Candles', 'Signage', 'Miscellaneous'];

    /** Context for the next movement log entry, set by the controller before saving. */
    public ?string $movementReason = null;

    public ?int $movementBookingId = null;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'min_threshold' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_threshold;
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'min_threshold');
    }

    public function stockLabel(): string
    {
        if ($this->quantity === 0) {
            return 'Out of Stock';
        }

        return $this->isLowStock() ? 'Low Stock' : 'Available';
    }

    public function stockBadge(): string
    {
        if ($this->quantity === 0) {
            return 'red';
        }

        return $this->isLowStock() ? 'amber' : 'green';
    }
}
