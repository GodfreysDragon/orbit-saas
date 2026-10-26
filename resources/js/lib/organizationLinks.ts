import { usePage } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { dashboard as organizationDashboard } from '@/routes/organization';

/**
 * The dashboard of the page's organization, so the link stays in this tab's
 * organization. Outside any organization it falls back to the app's entry
 * point, which picks one.
 */
export function dashboardLink() {
    return usePage().props?.auth?.organizationSlug
        ? organizationDashboard()
        : dashboard();
}
