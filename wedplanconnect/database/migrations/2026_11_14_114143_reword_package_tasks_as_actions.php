<?php

use App\Models\Booking;
use App\Models\Task;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Package tasks used to be titled with the bare inclusion ("Cake"); they are now worded as
     * actions ("Order the wedding cake") with their own instructions. Rename existing ones in place,
     * keeping their status, due date and history.
     */
    public function up(): void
    {
        // Old title (the inclusion as printed) => [new title, package, task details], across every package.
        $renames = collect(Booking::packages())->keys()
            ->flatMap(fn (string $package) => collect(Booking::packageTasks($package))
                ->mapWithKeys(fn (array $spec, string $title) => [$spec['inclusion'] => [$title, $spec]]))
            ->all();

        Task::withTrashed()
            ->whereNotNull('booking_id')
            ->whereIn('title', array_keys($renames))
            ->with('booking')
            ->each(function (Task $task) use ($renames) {
                [$title, $spec] = $renames[$task->title];
                $package = $task->booking?->package;

                $task->title = $title;

                if (array_key_exists((string) $package, Booking::packages())) {
                    $task->description = Booking::packageTaskDescription($package, $spec);
                }

                $task->saveQuietly();
            });
    }

    /**
     * Restore the bare inclusion titles.
     */
    public function down(): void
    {
        $renames = collect(Booking::packages())->keys()
            ->flatMap(fn (string $package) => collect(Booking::packageTasks($package))
                ->mapWithKeys(fn (array $spec, string $title) => [$title => $spec['inclusion']]))
            ->all();

        Task::withTrashed()
            ->whereNotNull('booking_id')
            ->whereIn('title', array_keys($renames))
            ->each(fn (Task $task) => $task->forceFill(['title' => $renames[$task->title]])->saveQuietly());
    }
};
