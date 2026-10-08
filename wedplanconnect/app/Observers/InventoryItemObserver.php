<?php

namespace App\Observers;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\Auth;

/**
 * Keeps inventory availability in sync with quantity (Out of Stock at zero) and
 * records every stock change for the Inventory Movement Report.
 */
class InventoryItemObserver
{
    public function saving(InventoryItem $item): void
    {
        $item->availability = (int) $item->quantity === 0 ? 'out_of_stock' : 'available';
    }

    public function created(InventoryItem $item): void
    {
        $this->logMovement($item, (int) $item->quantity, $item->movementReason ?? 'Initial stock');
    }

    public function updated(InventoryItem $item): void
    {
        if (! $item->wasChanged('quantity')) {
            return;
        }

        $change = (int) $item->quantity - (int) $item->getOriginal('quantity');
        $this->logMovement($item, $change, $item->movementReason ?? ($change > 0 ? 'Restock' : 'Stock used'));
    }

    private function logMovement(InventoryItem $item, int $change, string $reason): void
    {
        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $item->movementBookingId,
            'user_id' => Auth::id(),
            'change' => $change,
            'quantity_after' => (int) $item->quantity,
            'reason' => $reason,
        ]);

        $item->movementReason = null;
        $item->movementBookingId = null;
    }
}
