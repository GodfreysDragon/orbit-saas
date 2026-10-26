<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-owned: queries are scoped to the current
 * organization and new records are stamped with its id automatically.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (self $model): void {
            $model->organization_id ??= app(CurrentOrganization::class)->id();
        });
    }

    /**
     * Query every organization's rows. Cross-tenant reads must ask for it
     * explicitly, because the organization scope fails closed.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function acrossOrganizations(Builder $query): void
    {
        $query->withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
