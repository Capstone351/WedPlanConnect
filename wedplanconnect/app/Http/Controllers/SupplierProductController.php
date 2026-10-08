<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vendor offerings (supplies, products, services).
 *
 * Vendors manage their own products at /vendor/products. Admins and planners manage any
 * supplier's products at /suppliers/{supplier}/products (for suppliers without a vendor login).
 * Images are stored on the private disk and served through an authenticated route.
 */
class SupplierProductController extends Controller
{
    private const DISK = 'local';

    public function index(Request $request): View
    {
        $supplier = $this->supplier($request);

        return view('products.index', [
            'supplier' => $supplier,
            'products' => $supplier->products()->withCount('bookingProducts')->orderByDesc('is_available')->orderBy('name')->get(),
            'ctx' => $this->context($request, $supplier),
        ]);
    }

    public function create(Request $request): View
    {
        $supplier = $this->supplier($request);

        return view('products.form', [
            'supplier' => $supplier,
            'product' => new SupplierProduct(['is_available' => true]),
            'ctx' => $this->context($request, $supplier),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = $this->supplier($request);
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('product-images', self::DISK);
        }

        $product = $supplier->products()->create($data);
        $ctx = $this->context($request, $supplier);

        return redirect()->route($ctx['prefix'].'.index', $ctx['params'])->with('status', "“{$product->name}” added to your offerings.");
    }

    public function edit(Request $request): View
    {
        $supplier = $this->supplier($request);

        return view('products.form', [
            'supplier' => $supplier,
            'product' => $this->product($request, $supplier),
            'ctx' => $this->context($request, $supplier),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $supplier = $this->supplier($request);
        $product = $this->product($request, $supplier);
        $data = $this->validated($request);

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            if ($product->image_path) {
                Storage::disk(self::DISK)->delete($product->image_path);
            }
            $data['image_path'] = $request->hasFile('image') ? $request->file('image')->store('product-images', self::DISK) : null;
        }

        $product->update($data);
        $ctx = $this->context($request, $supplier);

        return redirect()->route($ctx['prefix'].'.index', $ctx['params'])->with('status', "“{$product->name}” updated.");
    }

    /** Soft delete: the product disappears from the catalog but stays on bookings that already use it. */
    public function destroy(Request $request): RedirectResponse
    {
        $supplier = $this->supplier($request);
        $product = $this->product($request, $supplier);
        $product->delete();

        return back()->with('status', "“{$product->name}” removed from your offerings.");
    }

    /** Serves a product photo to signed-in users only. */
    public function image(SupplierProduct $product): StreamedResponse
    {
        abort_unless($product->image_path && Storage::disk(self::DISK)->exists($product->image_path), 404);

        return Storage::disk(self::DISK)->response($product->image_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    private function supplier(Request $request): Supplier
    {
        if ($request->user()->role === 'vendor') {
            return $request->user()->supplierProfile
                ?? abort(403, 'Your account is not linked to a supplier profile yet. Please contact the administrator.');
        }

        return Supplier::findOrFail($request->route('supplier'));
    }

    private function product(Request $request, Supplier $supplier): SupplierProduct
    {
        return $supplier->products()->findOrFail($request->route('product'));
    }

    /** Route name prefix and parameters for the current portal (vendor or staff). */
    private function context(Request $request, Supplier $supplier): array
    {
        return $request->user()->role === 'vendor'
            ? ['prefix' => 'vendor.products', 'params' => [], 'vendor' => true]
            : ['prefix' => 'suppliers.products', 'params' => [$supplier->id], 'vendor' => false];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'unit' => ['nullable', 'string', 'max:30'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], ['image.max' => 'The photo must be 2 MB or smaller.']);

        unset($data['image']);
        $data['is_available'] = $request->boolean('is_available');

        return $data;
    }
}
