<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * UC-06 Manage Decoration Inventory.
 */
class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $items = InventoryItem::query()
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->boolean('low'), fn ($q) => $q->lowStock())
            ->orderBy('category')->orderBy('name')
            ->paginate(25)->withQueryString();

        $stats = [
            'items' => InventoryItem::count(),
            'units' => (int) InventoryItem::sum('quantity'),
            'low' => InventoryItem::lowStock()->count(),
            'value' => (float) InventoryItem::selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as v')->value('v'),
        ];

        return view('inventory.index', [
            'items' => $items,
            'stats' => $stats,
            'lowItems' => InventoryItem::lowStock()->orderBy('quantity')->get(),
            'categories' => InventoryItem::CATEGORIES,
            'bookings' => Booking::visibleTo($request->user())->active()->orderBy('event_date')->get(['id', 'client_name', 'event_date']),
        ]);
    }

    public function create(): View
    {
        return view('inventory.form', ['item' => new InventoryItem(['quantity' => 0, 'min_threshold' => 5, 'unit' => 'pcs']), 'categories' => InventoryItem::CATEGORIES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $item = InventoryItem::create($this->validated($request));

        return redirect()->route('inventory.index')->with('status', "{$item->name} added to inventory.".$this->lowStockNote($item));
    }

    public function edit(InventoryItem $item): View
    {
        return view('inventory.form', ['item' => $item, 'categories' => InventoryItem::CATEGORIES]);
    }

    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $item->movementReason = 'Manual correction';
        $item->update($this->validated($request));

        return redirect()->route('inventory.index')->with('status', "{$item->name} updated.".$this->lowStockNote($item));
    }

    /** Stock in / stock out with an optional booking commitment, logged as a movement. */
    public function adjust(Request $request, InventoryItem $item): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'integer', 'min:1', 'max:999999'],
            'booking_id' => ['nullable', Rule::exists('bookings', 'id')],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $delta = $data['direction'] === 'in' ? $data['amount'] : -$data['amount'];

        if ($item->quantity + $delta < 0) {
            throw ValidationException::withMessages(['amount' => "Only {$item->quantity} {$item->unit} of {$item->name} are in stock."]);
        }

        $item->movementReason = ($data['reason'] ?? null) ?: ($delta > 0 ? 'Restock' : 'Used for event');
        $item->movementBookingId = $data['booking_id'] ?? null;
        $item->quantity += $delta;
        $item->save();

        return back()->with('status', "{$item->name}: stock is now {$item->quantity} {$item->unit}.".$this->lowStockNote($item));
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        $item->delete();

        return back()->with('status', "{$item->name} removed from inventory.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(InventoryItem::CATEGORIES)],
            'quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'unit' => ['required', 'string', 'max:20'],
            'min_threshold' => ['required', 'integer', 'min:0', 'max:999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);
    }

    private function lowStockNote(InventoryItem $item): string
    {
        return $item->isLowStock() ? " ⚠ {$item->name} is at or below its minimum threshold ({$item->min_threshold})." : '';
    }
}
