<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vendor inventory: one table of the supplier's products where they update how many are in stock,
 * the low-stock warning level, and whether each product is available to planners and couples.
 */
class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $products = $this->supplier($request)->products()->withReserved()
            ->orderByDesc('is_available')->orderBy('name')->get();

        return view('vendor.inventory', [
            'products' => $products,
            'counts' => [
                'products' => $products->count(),
                'out' => $products->filter(fn (SupplierProduct $p) => $p->unitsLeft() === 0)->count(),
                'low' => $products->filter(fn (SupplierProduct $p) => $p->unitsLeft() !== null && $p->unitsLeft() > 0 && $p->unitsLeft() <= $p->low_stock_threshold)->count(),
                'hidden' => $products->where('is_available', false)->count(),
            ],
        ]);
    }

    public function update(Request $request, int $product): RedirectResponse
    {
        $product = $this->supplier($request)->products()->findOrFail($product);

        $data = $request->validate([
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:10000'],
        ], ['stock_quantity.min' => 'Stock cannot be negative.']);

        $product->update([...$data, 'is_available' => $request->boolean('is_available')]);
        [$label] = $product->stockStatus();

        return back()->with('status', "{$product->name} updated: ".($product->tracksStock() ? "{$product->stock_quantity} in stock ({$label})" : 'stock not counted')
            .($product->is_available ? '.' : ', hidden from planners and couples.'));
    }

    private function supplier(Request $request): Supplier
    {
        return $request->user()->supplierProfile
            ?? abort(403, 'Your account is not linked to a supplier profile yet. Please contact the administrator.');
    }
}
