<?php

use App\Models\Task;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Automatic overdue detection for the Task Assignment & Monitoring module.
Artisan::command('tasks:flag-overdue', function () {
    $flagged = Task::open()
        ->whereDate('due_date', '<', today())
        ->where('is_overdue', false)
        ->update(['is_overdue' => true]);

    $this->info("Flagged {$flagged} overdue task(s).");
})->purpose('Flag incomplete tasks whose due date has passed');

Schedule::command('tasks:flag-overdue')->daily();
