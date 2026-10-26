<?php

namespace App\Models\Scopes;

use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts queries to rows owned by the current organization.
 *
 * Within an HTTP request the tenant is always resolved by the `organization`
 * middleware, and queued jobs get the tenant they were dispatched from (see
 * TenantAwareJobs). Without a tenant (console commands, NotTenantAware jobs)
 * the scope fails closed and matches nothing: call
 * CurrentOrganization::runAs(), or opt in to every tenant's rows explicitly
 * with Model::acrossOrganizations().
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class OrganizationScope implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = app(CurrentOrganization::class)->id();

        if ($organizationId === null) {
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($model->qualifyColumn('organization_id'), $organizationId);
    }
}
