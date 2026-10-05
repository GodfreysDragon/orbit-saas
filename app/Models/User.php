<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use App\Enums\Permission;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_organization_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Membership|null $membership Set when loaded through Organization::users()
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Organization, $this, Membership, 'membership'>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(Membership::class)
            ->as('membership')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Organization, $this>
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()->whereKey($organization->getKey())->exists();
    }

    public function switchOrganization(Organization $organization): void
    {
        $this->forceFill(['current_organization_id' => $organization->getKey()])->save();

        $this->setRelation('currentOrganization', $organization);
    }

    /**
     * Fall back to another organization, preferring the personal one
     * (e.g. after leaving or deleting the current organization).
     */
    public function switchToFallbackOrganization(): ?Organization
    {
        $fallback = $this->organizations()
            ->orderByDesc('personal')
            ->orderBy('organizations.id')
            ->first();

        $this->forceFill(['current_organization_id' => $fallback?->getKey()])->save();
        $this->setRelation('currentOrganization', $fallback);

        return $fallback;
    }

    /**
     * The user's role inside the given organization.
     */
    public function roleIn(Organization $organization): ?OrganizationRole
    {
        return $this->withinOrganization($organization, function (): ?OrganizationRole {
            $role = $this->roles->first();

            return $role instanceof Role ? OrganizationRole::tryFrom($role->name) : null;
        });
    }

    /**
     * Check a permission against the user's role in a specific organization,
     * regardless of which organization is currently active.
     */
    public function hasPermissionIn(Organization $organization, Permission $permission): bool
    {
        return $this->withinOrganization(
            $organization,
            fn (): bool => $this->hasPermissionTo($permission->value),
        );
    }

    /**
     * Permission names the user holds inside the given organization.
     *
     * @return list<string>
     */
    public function permissionsIn(Organization $organization): array
    {
        return $this->withinOrganization(
            $organization,
            fn (): array => array_values($this->getAllPermissions()
                ->map(fn (PermissionModel $permission): string => $permission->name)
                ->all()),
        );
    }

    /**
     * Evaluate a callback with Spatie's team context switched to the given
     * organization. Cached role relations are cleared on the way in and out
     * so roles from different organizations never bleed into each other.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withinOrganization(Organization $organization, callable $callback): mixed
    {
        $previousTeamId = getPermissionsTeamId();

        if ($previousTeamId === $organization->getKey()) {
            return $callback();
        }

        setPermissionsTeamId($organization->getKey());
        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previousTeamId);
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}
