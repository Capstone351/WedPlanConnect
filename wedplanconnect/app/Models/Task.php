<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['booking_id', 'assigned_to', 'title', 'description', 'due_date', 'priority', 'status'])]
class Task extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['pending', 'ongoing', 'completed'];

    public const PRIORITIES = ['low', 'medium', 'high'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_overdue' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keep the overdue flag consistent on every save; the daily scheduler covers date rollover.
        static::saving(function (Task $task) {
            $task->is_overdue = $task->status !== 'completed'
                && $task->due_date !== null
                && $task->due_date->lt(today());
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', 'completed');
    }

    /** Open tasks past their due date, computed live so it never depends on the scheduler having run. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', today());
    }

    /** Live overdue state; the stored column is kept in sync by the scheduler for record-keeping. */
    protected function isOverdue(): Attribute
    {
        return Attribute::get(fn () => $this->status !== 'completed'
            && $this->due_date !== null
            && $this->due_date->lt(today()));
    }

    /** Tasks visible to a user: admins see all, planners their assigned tasks or tasks on their bookings. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === 'admin') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('assigned_to', $user->id)
                ->orWhereHas('booking', fn (Builder $b) => $b->where('planner_id', $user->id));
        });
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            'completed' => 'green',
            'ongoing' => 'blue',
            default => 'amber',
        };
    }

    public static function priorityBadge(string $priority): string
    {
        return match ($priority) {
            'high' => 'red',
            'medium' => 'amber',
            default => 'gray',
        };
    }
}
