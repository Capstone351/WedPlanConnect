<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['supplier_id', 'name', 'description', 'is_package', 'guest_capacity', 'inclusions', 'price', 'unit', 'stock_quantity', 'low_stock_threshold', 'is_available', 'image_path'])]
class SupplierProduct extends Model
{
    use SoftDeletes;

    public const UNITS = ['per package', 'per set', 'per piece', 'per pax', 'per hour', 'per event'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_available' => 'boolean',
            'is_package' => 'boolean',
            'guest_capacity' => 'integer',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    /** Adds reserved_quantity: units already ordered for upcoming pending or confirmed weddings. */
    public function scopeWithReserved(Builder $query): Builder
    {
        return $query->withSum(['bookingProducts as reserved_quantity' => fn ($q) => $q->whereHas('booking', fn ($b) => $b->withoutTrashed()->upcoming())], 'quantity');
    }

    public function tracksStock(): bool
    {
        return $this->stock_quantity !== null;
    }

    public function reservedQuantity(): int
    {
        return (int) (array_key_exists('reserved_quantity', $this->attributes)
            ? $this->reserved_quantity
            : $this->newQuery()->whereKey($this->id)->withReserved()->value('reserved_quantity'));
    }

    /** Units still free to order: in stock minus units ordered for upcoming weddings. Null when stock isn't counted. */
    public function unitsLeft(): ?int
    {
        return $this->tracksStock() ? max(0, $this->stock_quantity - $this->reservedQuantity()) : null;
    }

    /**
     * Label and badge colour for the stock level, e.g. ["Low stock: 3 left", "amber"].
     *
     * @return array{0: string, 1: string}
     */
    public function stockStatus(): array
    {
        $left = $this->unitsLeft();

        return match (true) {
            $left === null => ['Not counted', 'gray'],
            $left === 0 => ['Out of stock', 'red'],
            $left <= $this->low_stock_threshold => ["Low stock: {$left} left", 'amber'],
            default => ["{$left} left", 'green'],
        };
    }

    /** Packages that serve at least the given number of guests. */
    public function scopePackagesFor(Builder $query, int $guests): Builder
    {
        return $query->where('is_package', true)->where('guest_capacity', '>=', $guests);
    }

    /**
     * What a package includes, one entry per line as the supplier typed it.
     *
     * @return array<int, string>
     */
    public function inclusionList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->inclusions))));
    }

    public function guestLabel(): ?string
    {
        return $this->is_package && $this->guest_capacity ? 'Good for '.number_format($this->guest_capacity).' guests' : null;
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
