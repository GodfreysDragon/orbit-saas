<?php

namespace App\Models;

use App\Billing\Plan;
use Carbon\CarbonImmutable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;

/**
 * The tenant. Organizations own every tenant-scoped resource and are the
 * Cashier "customer": subscriptions belong to the organization, not a user.
 *
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $slug
 * @property bool $personal
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['owner_id', 'name', 'slug', 'personal', 'trial_ends_at'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Billable, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            $organization->slug ??= static::uniqueSlug($organization->name);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'personal' => 'boolean',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this, Membership, 'membership'>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->as('membership')
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function hasUser(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->exists();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->getKey();
    }

    /**
     * Resolve the plan the organization is currently entitled to.
     */
    public function plan(): Plan
    {
        $subscription = $this->subscription();

        if ($subscription?->valid()) {
            return Plan::findByStripePrice($subscription->stripe_price) ?? Plan::free();
        }

        if ($this->onGenericTrial()) {
            return Plan::trial();
        }

        return Plan::free();
    }

    /**
     * Seats are consumed by members and by pending invitations.
     */
    public function seatsUsed(): int
    {
        return $this->users()->count() + $this->invitations()->count();
    }

    public function canAddMembers(int $count = 1): bool
    {
        $limit = $this->plan()->maxMembers;

        return $limit === null || $this->seatsUsed() + $count <= $limit;
    }

    public function canCreateProjects(): bool
    {
        $limit = $this->plan()->maxProjects;

        return $limit === null || $this->projects()->count() < $limit;
    }

    /**
     * The name shown on Stripe invoices and in the billing portal.
     */
    public function stripeName(): ?string
    {
        return $this->name;
    }

    /**
     * Billing emails go to the organization owner.
     */
    public function stripeEmail(): ?string
    {
        return $this->owner?->email;
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
