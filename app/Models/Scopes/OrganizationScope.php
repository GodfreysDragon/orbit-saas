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
 * middleware. Console commands and queued jobs have no implicit tenant, so
 * they must either call CurrentOrganization::runAs() or filter explicitly.
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

        if ($organizationId !== null) {
            $builder->where($model->qualifyColumn('organization_id'), $organizationId);
        }
    }
}
