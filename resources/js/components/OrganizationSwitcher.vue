<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, Check, ChevronsUpDown, Plus, User } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { initials } from '@/lib/format';
import { dashboard } from '@/routes/organization';
import { create } from '@/routes/organizations';
import type { OrganizationSummary } from '@/types';

const page = usePage();
const { isMobile } = useSidebar();

const current = computed(() => page.props.auth.organization);
const organizations = computed(() => page.props.auth.organizations ?? []);

function switchTo(organization: OrganizationSummary) {
    if (organization.id === current.value?.id) {
        return;
    }

    // The organization is in the URL, so switching only changes this tab.
    router.visit(dashboard(organization.slug));
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="organization-switcher"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-xs font-semibold text-sidebar-primary-foreground"
                        >
                            {{ current ? initials(current.name) : '?' }}
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-semibold">
                                {{ current?.name ?? 'No organization' }}
                            </span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ current?.plan.name }} plan
                                <template v-if="current?.role">
                                    &middot; {{ current.role.label }}
                                </template>
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-60 rounded-lg"
                    align="start"
                    :side="isMobile ? 'bottom' : 'right'"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        Organizations
                    </DropdownMenuLabel>
                    <DropdownMenuItem
                        v-for="organization in organizations"
                        :key="organization.id"
                        class="cursor-pointer gap-2 p-2"
                        @select="switchTo(organization)"
                    >
                        <div
                            class="flex size-6 items-center justify-center rounded-sm border"
                        >
                            <User
                                v-if="organization.personal"
                                class="size-3.5 shrink-0"
                            />
                            <Building2 v-else class="size-3.5 shrink-0" />
                        </div>
                        <span class="truncate">{{ organization.name }}</span>
                        <Check
                            v-if="organization.id === current?.id"
                            class="ml-auto size-4"
                        />
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child class="cursor-pointer gap-2 p-2">
                        <Link :href="create()">
                            <div
                                class="flex size-6 items-center justify-center rounded-md border bg-background"
                            >
                                <Plus class="size-4" />
                            </div>
                            <span class="font-medium text-muted-foreground">
                                New organization
                            </span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
