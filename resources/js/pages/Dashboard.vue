<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CheckCircle2,
    Circle,
    CreditCard,
    FolderKanban,
    Mail,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivityChart from '@/components/ActivityChart.vue';
import ActivityFeed from '@/components/ActivityFeed.vue';
import PageGuide from '@/components/PageGuide.vue';
import StatCard from '@/components/StatCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import UsageMeter from '@/components/UsageMeter.vue';
import { usePermissions } from '@/composables/usePermissions';
import { daysUntil, formatDate, timeAgo } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as activityIndex } from '@/routes/activity';
import { index as billingIndex } from '@/routes/billing';
import { index as membersIndex } from '@/routes/members';
import { index as projectsIndex } from '@/routes/projects';
import { edit as securityEdit } from '@/routes/security';
import type { ActivityItem, BillingSummary, Project, Usage } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const props = defineProps<{
    stats: {
        members: number;
        pendingInvitations: number;
        projects: number;
        activeProjects: number;
    };
    usage: { members: Usage; projects: Usage };
    billing: BillingSummary;
    recentProjects: Pick<
        Project,
        'id' | 'name' | 'status' | 'creator' | 'createdAt'
    >[];
    activityTrend?: { date: string; count: number }[] | null;
    recentActivity?: ActivityItem[] | null;
    onboarding: {
        project: boolean;
        teammate: boolean;
        plan: boolean;
        twoFactor: boolean;
    };
}>();

const page = usePage();
const { can } = usePermissions();
const user = computed(() => page.props.auth.user);
const organization = computed(() => page.props.auth.organization);

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';
    return 'Good evening';
});

const checklist = computed(() => [
    {
        done: props.onboarding.project,
        title: 'Create your first project',
        href: projectsIndex(),
    },
    {
        done: props.onboarding.teammate,
        title: 'Invite a teammate',
        href: membersIndex(),
    },
    {
        done: props.onboarding.plan,
        title: 'Choose a plan',
        href: billingIndex(),
        hidden: !can('billing.manage'),
    },
    {
        done: props.onboarding.twoFactor,
        title: 'Enable two-factor authentication',
        href: securityEdit(),
    },
]);
const visibleChecklist = computed(() =>
    checklist.value.filter((item) => !item.hidden),
);
const checklistDone = computed(
    () => visibleChecklist.value.filter((item) => item.done).length,
);
const checklistComplete = computed(
    () => checklistDone.value === visibleChecklist.value.length,
);

// The Setup tile jumps to the checklist and briefly highlights it.
const highlightChecklist = ref(false);
let highlightTimer: ReturnType<typeof setTimeout> | undefined;

function showChecklist(event: MouseEvent) {
    const checklistCard = document.getElementById('get-started');

    if (!checklistCard) return;

    event.preventDefault();

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)',
    ).matches;
    checklistCard.scrollIntoView({
        behavior: reduceMotion ? 'auto' : 'smooth',
        block: 'center',
    });
    checklistCard.focus({ preventScroll: true });

    highlightChecklist.value = true;
    clearTimeout(highlightTimer);
    highlightTimer = setTimeout(() => (highlightChecklist.value = false), 1600);
}

const statusLabel: Record<BillingSummary['status'], string> = {
    free: 'Free',
    trialing: 'Trial',
    active: 'Active',
    canceling: 'Canceling',
    past_due: 'Past due',
};

const projectStatusClass: Record<Project['status'], string> = {
    active: 'bg-emerald-500',
    paused: 'bg-amber-500',
    archived: 'bg-muted-foreground/50',
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ greeting }}, {{ user.name.split(' ')[0] }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Here's what's happening in
                    <span class="font-medium text-foreground">{{
                        organization?.name
                    }}</span>
                    today.
                </p>
            </div>
            <Button v-if="can('projects.create')" as-child>
                <Link :href="projectsIndex()">
                    <FolderKanban class="size-4" /> New project
                </Link>
            </Button>
        </div>

        <PageGuide
            id="dashboard"
            summary="A snapshot of the organization you're working in: members, projects, plan and recent activity. Switch organizations from the top of the sidebar and everything here changes with it. Click Setup to jump to the onboarding checklist."
            :details="[
                'Every number is scoped to the current organization, which middleware resolves before the page loads.',
                'The activity chart is an Inertia deferred prop, so the page renders first and the chart streams in.',
                'Buttons and cards only appear when your role allows them. Log in as viewer@example.com to compare.',
                'Usage meters compare live counts with the plan limits in `config/billing.php`.',
            ]"
        />

        <!-- KPI row -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Members" :value="stats.members" :icon="Users">
                <template #hint>
                    {{ stats.pendingInvitations }} pending
                    {{
                        stats.pendingInvitations === 1
                            ? 'invitation'
                            : 'invitations'
                    }}
                </template>
            </StatCard>
            <StatCard
                label="Projects"
                :value="stats.projects"
                :icon="FolderKanban"
                :hint="`${stats.activeProjects} active`"
            />
            <StatCard
                label="Plan"
                :value="billing.plan.name"
                :icon="CreditCard"
            >
                <template #hint>
                    <template
                        v-if="
                            billing.status === 'trialing' && billing.trialEndsAt
                        "
                    >
                        Trial ends in {{ daysUntil(billing.trialEndsAt) }} days
                    </template>
                    <template
                        v-else-if="
                            billing.status === 'canceling' && billing.endsAt
                        "
                    >
                        Ends {{ formatDate(billing.endsAt) }}
                    </template>
                    <template v-else>{{
                        statusLabel[billing.status]
                    }}</template>
                </template>
            </StatCard>
            <StatCard
                label="Setup"
                :value="`${checklistDone}/${visibleChecklist.length}`"
                :icon="CheckCircle2"
                hint="Onboarding steps complete"
                :href="checklistComplete ? undefined : '#get-started'"
                data-test="setup-stat"
                @click="showChecklist"
            />
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Activity trend -->
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Activity</CardTitle>
                    <CardDescription
                        >Events recorded across your
                        organization</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <template v-if="can('activity.view')">
                        <Deferred data="activityTrend">
                            <template #fallback>
                                <Skeleton class="h-48 w-full" />
                            </template>
                            <ActivityChart :points="activityTrend ?? []" />
                        </Deferred>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">
                        Your role doesn't include access to the activity log.
                    </p>
                </CardContent>
            </Card>

            <!-- Usage -->
            <Card>
                <CardHeader>
                    <CardTitle>Plan usage</CardTitle>
                    <CardDescription>
                        Limits on the {{ billing.plan.name }} plan
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <UsageMeter label="Seats" :usage="usage.members" />
                    <UsageMeter label="Projects" :usage="usage.projects" />
                    <Button
                        v-if="can('billing.manage')"
                        variant="outline"
                        class="w-full"
                        as-child
                    >
                        <Link :href="billingIndex()">
                            Manage plan <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Recent projects -->
            <Card class="lg:col-span-2">
                <CardHeader class="flex flex-row items-center justify-between">
                    <div class="space-y-1.5">
                        <CardTitle>Recent projects</CardTitle>
                        <CardDescription
                            >The latest work in this
                            organization</CardDescription
                        >
                    </div>
                    <Button variant="ghost" size="sm" as-child>
                        <Link :href="projectsIndex()">
                            View all <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </CardHeader>
                <CardContent>
                    <ul v-if="recentProjects.length" class="divide-y">
                        <li
                            v-for="project in recentProjects"
                            :key="project.id"
                            class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <div
                                class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary"
                            >
                                {{ project.name.charAt(0) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ project.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ project.creator ?? 'Unknown' }} &middot;
                                    {{ timeAgo(project.createdAt) }}
                                </p>
                            </div>
                            <Badge variant="outline" class="gap-1.5 capitalize">
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="projectStatusClass[project.status]"
                                />
                                {{ project.status }}
                            </Badge>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        No projects yet.
                    </p>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-6">
                <!-- Onboarding -->
                <Card
                    v-if="!checklistComplete"
                    id="get-started"
                    tabindex="-1"
                    class="scroll-mt-6 transition-shadow duration-500 outline-none"
                    :class="
                        highlightChecklist &&
                        'ring-2 ring-primary ring-offset-2 ring-offset-background'
                    "
                >
                    <CardHeader>
                        <CardTitle>Get started</CardTitle>
                        <CardDescription
                            >{{ checklistDone }} of
                            {{ visibleChecklist.length }}
                            complete</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-1">
                            <li
                                v-for="item in visibleChecklist"
                                :key="item.title"
                            >
                                <Link
                                    :href="item.href"
                                    class="flex items-center gap-3 rounded-md px-2 py-2 text-sm transition-colors hover:bg-muted"
                                >
                                    <CheckCircle2
                                        v-if="item.done"
                                        class="size-4 text-primary"
                                    />
                                    <Circle
                                        v-else
                                        class="size-4 text-muted-foreground"
                                    />
                                    <span
                                        :class="
                                            item.done
                                                ? 'text-muted-foreground line-through'
                                                : ''
                                        "
                                        >{{ item.title }}</span
                                    >
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Recent activity -->
                <Card v-if="can('activity.view')" class="flex-1">
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle>Latest activity</CardTitle>
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="activityIndex()">View all</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Deferred data="recentActivity">
                            <template #fallback>
                                <div class="space-y-4">
                                    <Skeleton
                                        v-for="i in 4"
                                        :key="i"
                                        class="h-10 w-full"
                                    />
                                </div>
                            </template>
                            <ActivityFeed :items="recentActivity ?? []" />
                        </Deferred>
                    </CardContent>
                </Card>

                <Card v-else-if="stats.pendingInvitations">
                    <CardContent class="flex items-center gap-3 text-sm">
                        <Mail class="size-4 text-muted-foreground" />
                        {{ stats.pendingInvitations }} pending
                        {{
                            stats.pendingInvitations === 1
                                ? 'invitation'
                                : 'invitations'
                        }}
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
