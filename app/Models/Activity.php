<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An entry in an organization's audit log.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $causer_id
 * @property string $event
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $description
 * @property array<string, mixed>|null $properties
 * @property string|null $idempotency_key
 * @property CarbonImmutable $created_at
 */
#[Fillable(['organization_id', 'causer_id', 'event', 'subject_type', 'subject_id', 'description', 'properties', 'idempotency_key', 'created_at'])]
#[WithoutTimestamps]
class Activity extends Model
{
    use BelongsToOrganization;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record an activity. Falls back to the current tenant and the
     * authenticated user when they are not given explicitly.
     *
     * An activity with an idempotency key (e.g. a Stripe event id) is
     * recorded once: recording the same key again returns the first entry.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?Organization $organization = null,
        ?User $causer = null,
        ?string $idempotencyKey = null,
    ): ?self {
        $organizationId = $organization?->getKey() ?? app(CurrentOrganization::class)->id();

        if ($organizationId === null) {
            return null;
        }

        $attributes = [
            'organization_id' => $organizationId,
            'causer_id' => $causer?->getKey() ?? auth()->id(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties ?: null,
            'created_at' => now(),
        ];

        if ($idempotencyKey === null) {
            return static::create($attributes);
        }

        // The unique index decides between concurrent attempts. The lookup
        // after a conflict must not depend on the current tenant.
        return static::query()->acrossOrganizations()->createOrFirst(['idempotency_key' => $idempotencyKey], $attributes);
    }
}
