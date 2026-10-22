<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Building2,
    Check,
    CreditCard,
    Gauge,
    MailPlus,
    ShieldCheck,
    X,
} from '@lucide/vue';
import { ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import IntervalToggle from '@/components/IntervalToggle.vue';
import PlanCard from '@/components/PlanCard.vue';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';
import type { Plan } from '@/types';

defineProps<{
    plans: Plan[];
    trialDays: number;
    trialPlan: string;
    canRegister: boolean;
}>();

const sourceUrl = 'https://github.com/GodfreysDragon/orbit-saas';

const interval = ref<'monthly' | 'yearly'>('monthly');

const features = [
    {
        icon: Building2,
        title: 'Organizations',
        description:
            'Every user gets a personal workspace and can create or join more. Switch tenants from the sidebar.',
    },
    {
        icon: ShieldCheck,
        title: 'Roles per organization',
        description:
            'Owner, Admin, Member and Viewer, scoped per tenant. Be an Owner in one org and a Viewer in another.',
    },
    {
        icon: MailPlus,
        title: 'Invitations',
        description:
            'Queued email invites with expiry, seat-limit checks and verified-email matching on accept.',
    },
    {
        icon: CreditCard,
        title: 'Stripe billing',
        description:
            'Card-less trials, Checkout, plan swaps, cancel and resume, the customer portal and invoices.',
    },
    {
        icon: Gauge,
        title: 'Plan limits',
        description:
            'Seat and project limits enforced in policies, with upgrade prompts when a team hits them.',
    },
    {
        icon: Activity,
        title: 'Audit log',
        description:
            'An activity feed written by observers, actions and Stripe webhooks, charted on the dashboard.',
    },
];

const matrix = {
    roles: ['Owner', 'Admin', 'Member', 'Viewer'],
    rows: [
        { label: 'Create projects', grants: [true, true, true, false] },
        { label: 'Invite members', grants: [true, true, false, false] },
        { label: 'Manage billing', grants: [true, false, false, false] },
        { label: 'Delete organization', grants: [true, false, false, false] },
    ],
};
</script>

<template>
    <Head title="Multi-tenant SaaS starter" />

    <div class="min-h-screen bg-background text-foreground">
        <!-- Nav -->
        <header class="border-b">
            <nav
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6"
            >
                <Link :href="home()" class="flex items-center gap-2">
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5" />
                    </span>
                    <span class="font-semibold">{{ $page.props.name }}</span>
                </Link>
                <div class="flex items-center gap-2">
                    <a
                        href="#pricing"
                        class="hidden px-3 text-sm text-muted-foreground hover:text-foreground sm:inline"
                        >Pricing</a
                    >
                    <a
                        :href="sourceUrl"
                        class="hidden px-3 text-sm text-muted-foreground hover:text-foreground sm:inline"
                        >Source</a
                    >
                    <Button v-if="$page.props.auth.user" as-child>
                        <Link :href="dashboard()">Dashboard</Link>
                    </Button>
                    <template v-else>
                        <Button variant="ghost" as-child>
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button v-if="canRegister" as-child>
                            <Link :href="register()">Sign up</Link>
                        </Button>
                    </template>
                </div>
            </nav>
        </header>

        <main>
            <!-- Hero -->
            <section class="relative overflow-hidden">
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-x-0 -top-40 -z-0 mx-auto h-96 max-w-4xl rounded-full bg-primary/15 blur-3xl"
                />
                <div
                    class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:py-28"
                >
                    <div class="space-y-6">
                        <span
                            class="inline-flex items-center gap-2 rounded-full border bg-background/60 px-3 py-1 text-xs text-muted-foreground"
                        >
                            Laravel 13 · Vue 3 · Inertia · Stripe
                        </span>
                        <h1
                            class="text-4xl font-semibold tracking-tight text-balance sm:text-5xl"
                        >
                            The B2B SaaS foundation, already built.
                        </h1>
                        <p class="max-w-xl text-lg text-muted-foreground">
                            Organizations, per-tenant roles and permissions,
                            team invitations and Stripe subscription billing.
                            Isolated, tested and ready for your product.
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <Button
                                v-if="canRegister && !$page.props.auth.user"
                                size="lg"
                                as-child
                            >
                                <Link :href="register()">
                                    Start a {{ trialDays }}-day
                                    {{ trialPlan }} trial
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                            <Button v-else size="lg" as-child>
                                <Link :href="dashboard()">
                                    Go to dashboard
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                            <Button size="lg" variant="outline" as-child>
                                <a :href="sourceUrl">View the source</a>
                            </Button>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            No card required.
                        </p>
                    </div>

                    <!-- Permission matrix preview -->
                    <div
                        class="rounded-xl border bg-card p-6 shadow-xl shadow-primary/5"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium">
                                    Acme Inc · Roles
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Permissions are checked on every request and
                                    mirrored in the UI.
                                </p>
                            </div>
                        </div>
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b text-xs text-muted-foreground"
                                >
                                    <th class="pb-2 text-left font-medium">
                                        <span class="sr-only">Permission</span>
                                    </th>
                                    <th
                                        v-for="role in matrix.roles"
                                        :key="role"
                                        class="pb-2 text-center font-medium"
                                    >
                                        {{ role }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr v-for="row in matrix.rows" :key="row.label">
                                    <td class="py-3 pr-2">{{ row.label }}</td>
                                    <td
                                        v-for="(granted, i) in row.grants"
                                        :key="i"
                                        class="py-3 text-center"
                                    >
                                        <Check
                                            v-if="granted"
                                            class="mx-auto size-4 text-primary"
                                            aria-label="Allowed"
                                        />
                                        <X
                                            v-else
                                            class="mx-auto size-4 text-muted-foreground/50"
                                            aria-label="Not allowed"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Features -->
            <section class="border-t bg-muted/30">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <div class="mb-12 max-w-2xl space-y-3">
                        <h2 class="text-3xl font-semibold tracking-tight">
                            Everything a team account needs
                        </h2>
                        <p class="text-muted-foreground">
                            Tenant data is isolated by a global scope, and the
                            tenant is resolved before route model binding, so
                            another organization's records are simply a 404.
                        </p>
                    </div>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="feature in features"
                            :key="feature.title"
                            class="rounded-xl border bg-card p-6"
                        >
                            <span
                                class="mb-4 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <component :is="feature.icon" class="size-5" />
                            </span>
                            <h3 class="mb-1 font-medium">
                                {{ feature.title }}
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                {{ feature.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Pricing -->
            <section id="pricing" class="border-t">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <div
                        class="mb-12 flex flex-wrap items-end justify-between gap-6"
                    >
                        <div class="max-w-2xl space-y-3">
                            <h2 class="text-3xl font-semibold tracking-tight">
                                Simple, per-organization pricing
                            </h2>
                            <p class="text-muted-foreground">
                                Every new organization starts with a
                                {{ trialDays }}-day {{ trialPlan }} trial.
                            </p>
                        </div>
                        <IntervalToggle v-model="interval" />
                    </div>
                    <div class="grid gap-6 md:grid-cols-3">
                        <PlanCard
                            v-for="plan in plans"
                            :key="plan.key"
                            :plan="plan"
                            :interval="interval"
                        >
                            <template #footer>
                                <Button
                                    class="w-full"
                                    :variant="
                                        plan.highlighted ? 'default' : 'outline'
                                    "
                                    as-child
                                >
                                    <Link
                                        :href="
                                            $page.props.auth.user ||
                                            !canRegister
                                                ? dashboard()
                                                : register()
                                        "
                                    >
                                        {{
                                            plan.prices.monthly === 0
                                                ? 'Get started'
                                                : `Try ${plan.name}`
                                        }}
                                    </Link>
                                </Button>
                            </template>
                        </PlanCard>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm text-muted-foreground sm:px-6"
            >
                <p>
                    {{ $page.props.name }} is a demo app for an open-source
                    Laravel + Vue SaaS boilerplate.
                </p>
                <a :href="sourceUrl" class="hover:text-foreground"
                    >View on GitHub</a
                >
            </div>
        </footer>
    </div>
</template>
