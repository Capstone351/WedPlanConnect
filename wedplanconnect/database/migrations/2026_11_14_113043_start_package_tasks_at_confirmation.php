<?php

use App\Models\Booking;
use App\Models\Task;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Package tasks now start when the planner confirms a wedding, and their priority reflects
     * importance rather than lead time. Bring existing data in line with that.
     */
    public function up(): void
    {
        $titles = Booking::allPackageTaskTitles();

        // Untouched package tasks on weddings the planner hasn't accepted yet; they return on confirmation.
        Task::query()
            ->whereIn('title', $titles)
            ->where('status', 'pending')
            ->whereHas('booking', fn ($q) => $q->where('status', 'pending'))
            ->each(fn (Task $task) => $task->delete());

        // Confirmed weddings: refresh untouched package tasks (priority and due dates).
        Booking::query()
            ->where('status', 'confirmed')
            ->whereIn('package', array_keys(Booking::packages()))
            ->each(fn (Booking $booking) => $booking->syncPackageTasks());
    }

    /**
     * Archived tasks stay archived; confirming the booking recreates them.
     */
    public function down(): void
    {
        //
    }
};
