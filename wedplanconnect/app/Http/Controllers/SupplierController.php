<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * UC-07 Manage Supplier Catalog (Admin and Wedding Planner).
 */
class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->when($request->input('status') === 'inactive', fn ($q) => $q->onlyTrashed())
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('availability'), fn ($q) => $q->where('availability', $request->string('availability')))
            ->withCount(['bookingSuppliers as active_assignments' => fn ($q) => $q->whereHas('booking', fn ($b) => $b->active()), 'products'])
            ->with('user')
            ->orderBy('category')->orderBy('name')
            ->paginate(18)->withQueryString();

        return view('suppliers.index', ['suppliers' => $suppliers, 'categories' => Supplier::CATEGORIES]);
    }

    public function create(): View
    {
        return view('suppliers.form', ['supplier' => new Supplier(['availability' => 'available']), 'categories' => Supplier::CATEGORIES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validated($request));

        return redirect()->route('suppliers.index')->with('status', "{$supplier->name} added to the catalog.");
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', ['supplier' => $supplier, 'categories' => Supplier::CATEGORIES]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));

        return redirect()->route('suppliers.index')->with('status', "{$supplier->name} updated.");
    }

    /** Deactivate: removes the supplier from active listings (soft delete). */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete();

        return back()->with('status', "{$supplier->name} has been deactivated and removed from active listings.");
    }

    public function restore(Supplier $supplier): RedirectResponse
    {
        $supplier->restore();

        return back()->with('status', "{$supplier->name} has been reactivated.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(Supplier::CATEGORIES)],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starting_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'availability' => ['required', Rule::in(['available', 'unavailable'])],
        ], ['phone.regex' => 'Enter a valid phone number.']);
    }
}
