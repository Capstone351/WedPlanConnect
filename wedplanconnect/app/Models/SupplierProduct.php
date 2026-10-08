<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['supplier_id', 'name', 'description', 'price', 'unit', 'is_available', 'image_path'])]
class SupplierProduct extends Model
{
    use SoftDeletes;

    public const UNITS = ['per package', 'per set', 'per piece', 'per pax', 'per hour', 'per event'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function bookingProducts(): HasMany
    {
        return $this->hasMany(BookingProduct::class);
    }

    /** Available products of suppliers that are still listed in the catalog. */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('is_available', true)->whereHas('supplier');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? route('products.image', $this) : null;
    }

    public function priceLabel(): string
    {
        if ($this->price === null) {
            return 'Price on request';
        }

        return '₱'.number_format((float) $this->price, 2).($this->unit ? ' '.$this->unit : '');
    }
}
