<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;

abstract class Controller
{
    /** Planners may only act on bookings they manage. */
    protected function authorizeBooking(Booking $booking): void
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless(
            $user->role === 'planner' && $booking->planner_id === $user->id,
            403,
            'This booking is managed by another planner.'
        );
    }
}
