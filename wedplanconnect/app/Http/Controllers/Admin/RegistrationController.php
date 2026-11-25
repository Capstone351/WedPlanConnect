<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\RegistrationAnswered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Admin approves or rejects Wedding Planners and Suppliers who signed up themselves.
 */
class RegistrationController extends Controller
{
    public function approve(User $user): RedirectResponse
    {
        abort_unless($user->awaiting_approval, 404);

        DB::transaction(function () use ($user) {
            // A supplier's business joins the catalog only now, linked to their login.
            if ($user->role === 'vendor' && $user->pending_supplier) {
                Supplier::create([
                    'user_id' => $user->id,
                    'name' => $user->pending_supplier['name'],
                    'category' => $user->pending_supplier['category'],
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'availability' => 'available',
                ]);
            }

            // The Admin has vetted the person, so their email is treated as confirmed.
            $user->forceFill(['awaiting_approval' => false, 'pending_supplier' => null, 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        });

        $user->notify(new RegistrationAnswered($user, approved: true));

        return back()->with('status', "{$user->name} is approved and can now log in.");
    }

    /** A rejected sign-up is removed completely, so the email address can be used again. */
    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->awaiting_approval, 404);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        $user->notify(new RegistrationAnswered($user, approved: false, reason: $reason));
        $user->forceDelete();

        return back()->with('status', "{$user->name}'s sign-up was rejected and removed.");
    }
}
