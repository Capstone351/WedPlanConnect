<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The couple sets their wedding budget (total, guests, optional breakdown, notes)
 * so their Wedding Planner can plan within it.
 */
class BudgetController extends Controller
{
    public function edit(Request $request): View
    {
        $budget = $request->user()->budget()->with('allocations')->first();

        return view('client.budget', [
            'budget' => $budget,
            'categories' => ClientBudget::categories(),
            'amounts' => $budget ? $budget->allocations->pluck('amount', 'category') : collect(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Accept "250,000" as well as "250000".
        $request->merge([
            'total_amount' => str_replace([',', '₱', ' '], '', (string) $request->input('total_amount')),
            'allocations' => collect($request->input('allocations', []))
                ->map(fn ($v) => str_replace([',', '₱', ' '], '', (string) $v))->all(),
        ]);

        $data = $request->validate([
            'total_amount' => ['required', 'numeric', 'min:1', 'max:9999999999.99'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ], [
            'total_amount.required' => 'Please enter your total wedding budget.',
            'total_amount.min' => 'Please enter your total wedding budget.',
            'allocations.*.numeric' => 'Breakdown amounts must be numbers.',
        ]);

        $allocations = collect($data['allocations'] ?? [])
            ->only(ClientBudget::categories())
            ->map(fn ($v) => (float) $v)
            ->filter(fn ($v) => $v > 0);

        if ($allocations->sum() - (float) $data['total_amount'] > 0.001) {
            throw ValidationException::withMessages([
                'allocations' => 'Your breakdown adds up to ₱'.number_format($allocations->sum(), 2)
                    .', which is more than your total budget of ₱'.number_format((float) $data['total_amount'], 2).'. Lower some amounts or raise the total.',
            ]);
        }

        DB::transaction(function () use ($request, $data, $allocations) {
            $budget = ClientBudget::updateOrCreate(
                ['user_id' => $request->user()->id],
                ['total_amount' => $data['total_amount'], 'guest_count' => $data['guest_count'] ?? null, 'notes' => $data['notes'] ?? null],
            );

            $budget->allocations()->whereNotIn('category', $allocations->keys())->delete();
            foreach ($allocations as $category => $amount) {
                $budget->allocations()->updateOrCreate(['category' => $category], ['amount' => $amount]);
            }
        });

        return redirect()->route('client.budget')->with('status', 'Your budget is saved. Your Wedding Planner can now see it when planning your wedding.');
    }
}
