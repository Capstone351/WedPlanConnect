<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Give bookings made before package tasks existed their package's preparation tasks.
     */
    public function up(): void
    {
        Booking::query()
            ->whereIn('status', Booking::ACTIVE_STATUSES)
            ->whereIn('package', array_keys(Booking::packages()))
            ->each(fn (Booking $booking) => $booking->syncPackageTasks());
    }

    /**
     * Package tasks are ordinary tasks once created; planners may have worked on them, so they are kept.
     */
    public function down(): void
    {
        //
    }
};
