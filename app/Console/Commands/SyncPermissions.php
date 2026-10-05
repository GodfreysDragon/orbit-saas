<?php

namespace App\Console\Commands;

use App\Enums\OrganizationRole;
use App\Enums\Permission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Writes the roles and permissions declared in the OrganizationRole and
 * Permission enums to the database. Roles are global (organization_id null)
 * and are assigned to users per organization through Spatie's teams mode.
 *
 * Safe to run on every deploy.
 */
#[Signature('permissions:sync')]
#[Description('Sync roles and permissions from the application enums')]
class SyncPermissions extends Command
{
    public function handle(PermissionRegistrar $registrar): int
    {
        $registrar->forgetCachedPermissions();

        $previousTeamId = getPermissionsTeamId();
        setPermissionsTeamId(null);

        foreach (Permission::cases() as $permission) {
            PermissionModel::findOrCreate($permission->value, 'web');
        }

        PermissionModel::whereNotIn('name', Permission::values())->delete();

        foreach (OrganizationRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')->syncPermissions(
                array_map(fn (Permission $permission) => $permission->value, $role->permissions()),
            );
        }

        setPermissionsTeamId($previousTeamId);
        $registrar->forgetCachedPermissions();

        $this->components->info(sprintf(
            'Synced %d permissions across %d roles.',
            count(Permission::cases()),
            count(OrganizationRole::cases()),
        ));

        return self::SUCCESS;
    }
}
