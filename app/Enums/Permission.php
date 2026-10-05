<?php

namespace App\Enums;

/**
 * Every permission in the application. Permissions are granted to users
 * exclusively through organization roles (see OrganizationRole).
 */
enum Permission: string
{
    case ViewProjects = 'projects.view';
    case CreateProjects = 'projects.create';
    case UpdateProjects = 'projects.update';
    case DeleteProjects = 'projects.delete';

    case ViewMembers = 'members.view';
    case InviteMembers = 'members.invite';
    case UpdateMembers = 'members.update';
    case RemoveMembers = 'members.remove';

    case UpdateOrganization = 'organization.update';
    case DeleteOrganization = 'organization.delete';
    case ManageBilling = 'billing.manage';
    case ViewActivity = 'activity.view';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
