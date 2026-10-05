<script setup lang="ts">
import {
    BookOpen,
    CreditCard,
    FolderKanban,
    History,
    LayoutGrid,
    Settings2,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import OrganizationSwitcher from '@/components/OrganizationSwitcher.vue';
import TrialCard from '@/components/TrialCard.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { index as activity } from '@/routes/activity';
import { index as billing } from '@/routes/billing';
import { index as members } from '@/routes/members';
import { edit as organizationSettings } from '@/routes/organization';
import { index as projects } from '@/routes/projects';
import type { NavItem } from '@/types';

const { can } = usePermissions();

type GuardedNavItem = NavItem & { visible?: boolean };

const visibleItems = (items: GuardedNavItem[]): NavItem[] =>
    items.filter((item) => item.visible !== false);

const mainNavItems = computed(() =>
    visibleItems([
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        {
            title: 'Projects',
            href: projects(),
            icon: FolderKanban,
            visible: can('projects.view'),
        },
        {
            title: 'Activity',
            href: activity(),
            icon: History,
            visible: can('activity.view'),
        },
    ]),
);

const organizationNavItems = computed(() =>
    visibleItems([
        {
            title: 'Members',
            href: members(),
            icon: Users,
            visible: can('members.view'),
        },
        {
            title: 'Billing',
            href: billing(),
            icon: CreditCard,
            visible: can('billing.manage'),
        },
        { title: 'Settings', href: organizationSettings(), icon: Settings2 },
    ]),
);

const footerNavItems: NavItem[] = [
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <OrganizationSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" label="Platform" />
            <NavMain :items="organizationNavItems" label="Organization" />
        </SidebarContent>

        <SidebarFooter>
            <TrialCard />
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
