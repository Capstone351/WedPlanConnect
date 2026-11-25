<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['role', 'name', 'bride_name', 'groom_name', 'bride_first_name', 'bride_last_name', 'groom_first_name', 'groom_last_name', 'email', 'phone', 'password', 'bio'])]
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

    /** Roles that may sign up themselves, then wait for the Admin's approval before they can log in. */
    public const ROLES_NEEDING_APPROVAL = ['planner', 'vendor'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'awaiting_approval' => 'boolean',
            'pending_supplier' => 'array',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'wedding_date' => 'date',
            'wedding_date_alt' => 'date',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // The full names shown everywhere ("Ana Santos & Miguel Reyes") are built from the first and last names.
        static::saving(function (User $user) {
            foreach (['bride', 'groom'] as $who) {
                if ($user->isDirty(["{$who}_first_name", "{$who}_last_name"]) && $user->{"{$who}_first_name"}) {
                    $user->{"{$who}_name"} = trim($user->{"{$who}_first_name"}.' '.$user->{"{$who}_last_name"});
                }
            }
        });
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Accounts the Admin has let in: leaves out Wedding Planners and Suppliers whose sign-up is still waiting. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('awaiting_approval', false);
    }

    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query->where('awaiting_approval', true);
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
        $onePerson = ['required', 'string', 'max:60', 'not_regex:/[&\/+]|\s(and|at)\s/i'];

        return [
            'bride_first_name' => $onePerson, 'bride_last_name' => $onePerson,
            'groom_first_name' => $onePerson, 'groom_last_name' => $onePerson,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function coupleNameMessages(): array
    {
        $messages = [];

        foreach (['bride' => "bride's", 'groom' => "groom's"] as $who => $whose) {
            foreach (['first' => 'first name', 'last' => 'last name'] as $part => $label) {
                $messages["{$who}_{$part}_name.required"] = "Please enter the {$whose} {$label}.";
                $messages["{$who}_{$part}_name.not_regex"] = "Enter only the {$whose} {$label} here.";
            }
        }

        return $messages;
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

    /** Account state shown in User Management: Active, Pending (sign-up awaiting approval), Locked, or Inactive (soft-deleted). */
    public function accountStatus(): string
    {
        if ($this->trashed()) {
            return 'Inactive';
        }

        if ($this->awaiting_approval) {
            return 'Pending';
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
