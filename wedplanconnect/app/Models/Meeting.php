<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A meeting a couple asks to have with their Wedding Planner.
 * Requested by the couple, then confirmed or declined by the planner.
 */
#[Fillable(['client_id', 'planner_id', 'scheduled_at', 'mode', 'location', 'agenda', 'status', 'responded_at', 'response_note'])]
class Meeting extends Model
{
    use HasFactory;

    public const MODES = ['in_person' => 'In person', 'online' => 'Online (video call)'];

    /** Statuses that hold the planner's time slot. */
    public const OPEN_STATUSES = ['requested', 'confirmed'];

    /** Meetings closer together than this (in minutes) clash for the planner. */
    public const SLOT_MINUTES = 60;

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id')->withTrashed();
    }

    public function planner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'planner_id')->withTrashed();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->open()->where('scheduled_at', '>=', now());
    }

    /** Meetings visible to a user: admins see all, planners their own, clients theirs. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'admin' => $query,
            'planner' => $query->where('planner_id', $user->id),
            'client' => $query->where('client_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** An open meeting of the planner that overlaps the given time. */
    public static function findClash(int $plannerId, CarbonInterface $at, ?int $ignoreId = null): ?self
    {
        return self::open()->where('planner_id', $plannerId)
            ->whereBetween('scheduled_at', [$at->copy()->subMinutes(self::SLOT_MINUTES - 1), $at->copy()->addMinutes(self::SLOT_MINUTES - 1)])
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->first();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->mode] ?? $this->mode;
    }

    /** "Sat, Nov 14, 2026 · 2:30 PM" */
    public function when(): string
    {
        return $this->scheduled_at->format('D, M j, Y · g:i A');
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'confirmed' => 'green',
            'declined' => 'red',
            'cancelled' => 'gray',
            default => 'amber',
        };
    }
}
