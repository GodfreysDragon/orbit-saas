import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Permission checks for the current organization, mirroring App\Enums\Permission.
 * These only toggle UI; every action is authorized again on the server.
 */
export function usePermissions() {
    const page = usePage();

    const organization = computed(() => page.props.auth.organization);
    const permissions = computed(
        () => new Set(organization.value?.permissions ?? []),
    );

    const can = (permission: string): boolean =>
        permissions.value.has(permission);

    return { organization, can };
}
