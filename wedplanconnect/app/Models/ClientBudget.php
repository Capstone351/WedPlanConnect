<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['user_id', 'total_amount', 'guest_count', 'notes'])]
class ClientBudget extends Model
{
    /** Categories a couple can split their budget into: the supplier categories plus costs FMT doesn't supply. */
    public static function categories(): array
    {
        return [...Supplier::CATEGORIES, 'Catering', 'Venue', 'Other'];
    }

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'guest_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function allocated(): float
    {
        return (float) ($this->relationLoaded('allocations') ? $this->allocations->sum('amount') : $this->allocations()->sum('amount'));
    }

    public function unallocated(): float
    {
        return round((float) $this->total_amount - $this->allocated(), 2);
    }

    /**
     * Compares the couple's allocation for each category with the set-up items picked for it,
     * in the same order the couple sees their categories.
     *
     * @param  Collection<int, BookingProduct>  $items  set-up items with product.supplier loaded
     * @return Collection<string, array{category: string, allocated: float|null, selected: float, remaining: float|null}>
     */
    public function byCategory(Collection $items): Collection
    {
        $allocated = $this->allocations->mapWithKeys(fn (BudgetAllocation $a) => [$a->category => (float) $a->amount]);
        $selected = $items->groupBy(fn (BookingProduct $item) => $item->product->supplier->category)
            ->map(fn (Collection $group) => (float) $group->sum(fn (BookingProduct $item) => $item->subtotal() ?? 0));

        return collect(self::categories())
            ->merge($allocated->keys())->merge($selected->keys())->unique()
            ->filter(fn (string $category) => $allocated->has($category) || $selected->has($category))
            ->mapWithKeys(fn (string $category) => [$category => [
                'category' => $category,
                'allocated' => $allocated->get($category),
                'selected' => $selected->get($category, 0.0),
                'remaining' => $allocated->has($category) ? round($allocated[$category] - $selected->get($category, 0.0), 2) : null,
            ]]);
    }

    /** Budget per guest, when the guest count is known. */
    public function perGuest(): ?float
    {
        return $this->guest_count ? round((float) $this->total_amount / $this->guest_count, 2) : null;
    }
}
