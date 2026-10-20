<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role', 'name', 'bride_name', 'groom_name', 'email', 'phone', 'password', 'bio'])]
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
            'wedding_date' => 'date',
            'wedding_date_alt' => 'date',
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

    /**
     * Validation for the bride's and groom's names: one person's name in each field.
     *
     * @return array<string, array<int, string>>
     */
    public static function coupleNameRules(): array
    {
        $onePerson = ['required', 'string', 'max:100', 'not_regex:/[&\/+]|\s(and|at)\s/i'];

        return ['bride_name' => $onePerson, 'groom_name' => $onePerson];
    }

    /**
     * @return array<string, string>
     */
    public static function coupleNameMessages(): array
    {
        return [
            'bride_name.required' => "Please enter the bride's name.",
            'groom_name.required' => "Please enter the groom's name.",
            'bride_name.not_regex' => "Enter only the bride's name here.",
            'groom_name.not_regex' => "Enter only the groom's name here.",
        ];
    }

    /** "Ana Santos & Miguel Reyes", or null until both names are known. */
    public function coupleNames(): ?string
    {
        return $this->bride_name && $this->groom_name ? "{$this->bride_name} & {$this->groom_name}" : null;
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

    /** A couple-client's favorite suppliers from the catalog. */
    public function favoriteSuppliers(): HasMany
    {
        return $this->hasMany(SupplierPreference::class, 'user_id');
    }

    /** The Wedding Planner a couple-client chose. */
    public function preferredPlanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_planner_id')->withTrashed();
    }

    /** Couples who chose this planner. */
    public function clientsWhoChoseMe(): HasMany
    {
        return $this->hasMany(User::class, 'preferred_planner_id');
    }

    /** A couple-client's own wedding budget. */
    public function budget(): HasOne
    {
        return $this->hasOne(ClientBudget::class);
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
