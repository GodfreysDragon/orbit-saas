<?php

namespace App\Models;

use App\Enums\OrganizationRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Deliberately NOT tenant-scoped: invitations are looked up by token from
 * the invitee's session, whose current organization is a different tenant.
 * Within an organization, always go through $organization->invitations().
 *
 * The token in the invitation link is never stored: the table keeps its
 * SHA-256 hash, so a database leak doesn't hand out working invitation links.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $invited_by_id
 * @property string $email
 * @property OrganizationRole $role
 * @property string $token SHA-256 hash of the token in the invitation link
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['email', 'role', 'invited_by_id', 'token', 'expires_at'])]
#[Hidden(['token'])]
class OrganizationInvitation extends Model
{
    use MassPrunable;

    /**
     * The token for the invitation link. Only the SHA-256 hash is stored, so
     * this is set only on the instance that generated it.
     */
    public ?string $plainToken = null;

    protected static function booted(): void
    {
        static::creating(function (OrganizationInvitation $invitation): void {
            if ($invitation->getAttribute('token') === null) {
                $invitation->plainToken = self::newToken();
                $invitation->token = self::hashToken($invitation->plainToken);
            }

            $invitation->expires_at ??= now()->addDays(7);
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
            'role' => OrganizationRole::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isFor(?string $email): bool
    {
        return $email !== null && Str::lower($email) === Str::lower($this->email);
    }

    public static function newToken(): string
    {
        return Str::random(64);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Query for the invitation whose link carries this token.
     *
     * @return Builder<self>
     */
    public static function whereToken(string $token): Builder
    {
        return self::query()->where('token', self::hashToken($token));
    }

    /**
     * Find an invitation that can still be accepted, e.g. from a sign-up form.
     */
    public static function findPending(?string $token): ?self
    {
        if (blank($token)) {
            return null;
        }

        return self::whereToken($token)
            ->with('organization')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Expired invitations are removed by the scheduled `model:prune` command.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('expires_at', '<', now());
    }
}
