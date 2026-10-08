<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role', 'name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLES = [
        'admin' => 'Admin',
        'planner' => 'Wedding Planner',
        'client' => 'Couple-Client',
        'vendor' => 'Vendor/Supplier',
    ];

    /** Consecutive failed logins before the account is locked (manuscript: five). */
    public const MAX_FAILED_ATTEMPTS = 5;

    public const LOCKOUT_MINUTES = 15;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    /** Account state shown in User Management: Active, Locked, or Inactive (soft-deleted). */
    public function accountStatus(): string
    {
        if ($this->trashed()) {
            return 'Inactive';
        }

        return $this->isLocked() ? 'Locked' : 'Active';
    }

    public function plannedBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'planner_id');
    }

    public function clientBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function supplierProfile(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    public function dashboardRoute(): string
    {
        return match ($this->role) {
            'admin' => 'admin.dashboard',
            'planner' => 'planner.dashboard',
            'vendor' => 'vendor.dashboard',
            default => 'client.dashboard',
        };
    }
}
