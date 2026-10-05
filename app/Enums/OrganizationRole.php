<?php

namespace App\Enums;

/**
 * Roles a user can hold inside an organization. Each role maps to a fixed
 * set of permissions which `php artisan permissions:sync` writes to the
 * Spatie roles/permissions tables.
 */
enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full access, including billing and deleting the organization.',
            self::Admin => 'Manage members, projects and organization settings.',
            self::Member => 'Create and edit projects.',
            self::Viewer => 'Read-only access to projects and members.',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),
            self::Admin => array_values(array_filter(
                Permission::cases(),
                fn (Permission $permission) => ! in_array($permission, [
                    Permission::DeleteOrganization,
                    Permission::ManageBilling,
                ], true),
            )),
            self::Member => [
                Permission::ViewProjects,
                Permission::CreateProjects,
                Permission::UpdateProjects,
                Permission::ViewMembers,
                Permission::ViewActivity,
            ],
            self::Viewer => [
                Permission::ViewProjects,
                Permission::ViewMembers,
            ],
        };
    }

    /**
     * Roles that can be granted through invitations or role changes.
     * Ownership is never handed out this way.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Member, self::Viewer];
    }

    /**
     * @return array{value: string, label: string, description: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
        ];
    }
}
