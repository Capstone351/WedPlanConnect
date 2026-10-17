<?php

namespace App\Http\Controllers\Planner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingSupplier;
use App\Models\InventoryItem;
use App\Models\SupplierPreference;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $upcoming = Booking::visibleTo($user)->upcoming()->orderBy('event_date');
        $myBookingIds = Booking::visibleTo($user)->upcoming()->select('id');

        // Couples who chose this planner but don't have an active booking yet.
        $chosenMe = User::where('role', 'client')->where('preferred_planner_id', $user->id)
            ->whereDoesntHave('clientBookings', fn ($q) => $q->active())
            ->with('budget')->withCount('favoriteSuppliers')->latest()->get();

        // Favorites from couples this planner works with (booked or who chose them).
        $myClientIds = Booking::visibleTo($user)->upcoming()->pluck('client_id')->merge($chosenMe->pluck('id'))->unique();

        return view('planner.dashboard', [
            'featured' => (clone $upcoming)->first(),
            'upcoming' => (clone $upcoming)->limit(6)->get(),
            'counts' => [
                'upcoming' => (clone $upcoming)->count(),
                'suppliers' => BookingSupplier::whereIn('booking_id', $myBookingIds)->where('status', 'confirmed')->count(),
                'pendingTasks' => Task::visibleTo($user)->open()->count(),
                'overdue' => Task::visibleTo($user)->overdue()->count(),
            ],
            'tasks' => Task::visibleTo($user)->open()->with('booking')->orderBy('due_date')->limit(8)->get(),
            'lowStock' => InventoryItem::lowStock()->orderBy('quantity')->limit(5)->get(),
            'newPreferences' => SupplierPreference::whereIn('user_id', $myClientIds)->with('client', 'supplier')->latest()->limit(5)->get(),
            'chosenMe' => $chosenMe,
        ]);
    }
}
