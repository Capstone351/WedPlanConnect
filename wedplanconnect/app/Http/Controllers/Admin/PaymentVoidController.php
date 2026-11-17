<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Notifications\PaymentVoidAnswered;
use App\Notifications\PaymentVoided;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The admin answers a planner's request to void a payment: void it (kept on record, crossed out)
 * or keep it. Only the admin can remove money from the books.
 */
class PaymentVoidController extends Controller
{
    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        if (! $payment->hasVoidRequest()) {
            return back()->withErrors(['void' => 'This payment has no open void request.']);
        }

        $planner = $payment->voidRequester;
        $payment->forceFill(['voided_by' => $request->user()->id, 'void_reason' => $payment->void_request_reason])->save();
        $payment->clearVoidRequest();
        $payment->delete();

        $payment->booking->client?->notify(new PaymentVoided($payment, $request->user()->name));
        $planner?->notify(new PaymentVoidAnswered($payment, true, $request->user()->name));

        return back()->with('status', "Payment voided; {$payment->booking->client_name}'s balance was recalculated. The couple and planner have been told.");
    }

    public function keep(Request $request, Payment $payment): RedirectResponse
    {
        if (! $payment->hasVoidRequest()) {
            return back()->withErrors(['void' => 'This payment has no open void request.']);
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);

        $planner = $payment->voidRequester;
        $payment->clearVoidRequest();
        $planner?->notify(new PaymentVoidAnswered($payment, false, $request->user()->name, $data['note'] ?? null));

        return back()->with('status', 'Payment kept on record. The planner has been told.');
    }
}
