<script setup lang="ts">
import { Deferred, Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    CreditCard,
    ExternalLink,
    FileText,
    Receipt,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import IntervalToggle from '@/components/IntervalToggle.vue';
import PlanCard from '@/components/PlanCard.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { daysUntil, formatDate } from '@/lib/format';
import { cancel, checkout, index, portal, resume } from '@/routes/billing';
import type { BillingSummary, Plan } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Billing', href: index() }],
    },
});

type Invoice = {
    id: string;
    number: string | null;
    date: string;
    total: string;
    status: string;
    url: string | null;
};

const props = defineProps<{
    plans: Plan[];
    billing: BillingSummary;
    stripeConfigured: boolean;
    invoices?: Invoice[];
}>();

const interval = ref<'monthly' | 'yearly'>(props.billing.interval ?? 'monthly');
const processingPlan = ref<string | null>(null);

// A trial is not a purchase: until subscribed, only Free counts as current.
const isCurrent = (plan: Plan) =>
    props.billing.subscribed
        ? plan.key === props.billing.plan.key &&
          props.billing.interval === interval.value
        : plan.prices.monthly === 0 && props.billing.status === 'free';

const checkoutSucceeded = computed(
    () =>
        typeof window !== 'undefined' &&
        new URLSearchParams(window.location.search).get('checkout') ===
            'success',
);

function choose(plan: Plan) {
    router.post(
        checkout().url,
        { plan: plan.key, interval: interval.value },
        {
            preserveScroll: true,
            onStart: () => (processingPlan.value = plan.key),
            onFinish: () => (processingPlan.value = null),
        },
    );
}

function ctaLabel(plan: Plan): string {
    if (isCurrent(plan)) return 'Current plan';
    if (!plan.purchasable)
        return plan.prices.monthly === 0 ? 'Free forever' : 'Not configured';
    if (props.billing.subscribed) return `Switch to ${plan.name}`;
    return `Upgrade to ${plan.name}`;
}

const cancelOpen = ref(false);

function performCancel() {
    router.post(
        cancel().url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (cancelOpen.value = false),
        },
    );
}

const statusBadge = computed(() => {
    switch (props.billing.status) {
        case 'active':
            return {
                label: 'Active',
                class: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
            };
        case 'trialing':
            return {
                label: 'Trial',
                class: 'border-primary/30 bg-primary/10 text-primary',
            };
        case 'canceling':
            return {
                label: 'Canceling',
                class: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
            };
        case 'past_due':
            return {
                label: 'Past due',
                class: 'border-destructive/30 bg-destructive/10 text-destructive',
            };
        default:
            return { label: 'Free', class: '' };
    }
});
</script>

<template>
    <Head title="Billing" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Billing"
            description="Manage your organization's subscription, payment method and invoices."
        />

        <Alert v-if="!stripeConfigured">
            <AlertTriangle class="size-4" />
            <AlertTitle>Stripe is not configured</AlertTitle>
            <AlertDescription>
                Add <code>STRIPE_KEY</code>, <code>STRIPE_SECRET</code> and the
                <code>STRIPE_PRICE_*</code> ids to your <code>.env</code> to
                enable checkout. Plans and limits work without them.
            </AlertDescription>
        </Alert>

        <Alert v-if="checkoutSucceeded" class="border-emerald-500/30">
            <Check class="size-4 text-emerald-600" />
            <AlertTitle>Thanks for subscribing!</AlertTitle>
            <AlertDescription>
                Your subscription is being activated. It can take a few seconds
                for Stripe to confirm the payment.
            </AlertDescription>
        </Alert>

        <!-- Current subscription -->
        <Card>
            <CardHeader
                class="flex flex-row flex-wrap items-start justify-between gap-4"
            >
                <div class="space-y-1.5">
                    <CardTitle class="flex items-center gap-2">
                        {{ billing.plan.name }} plan
                        <Badge variant="outline" :class="statusBadge.class">
                            {{ statusBadge.label }}
                        </Badge>
                    </CardTitle>
                    <CardDescription>
                        <template
                            v-if="
                                billing.status === 'trialing' &&
                                billing.trialEndsAt
                            "
                        >
                            Your trial ends on
                            {{ formatDate(billing.trialEndsAt) }}
                            ({{ daysUntil(billing.trialEndsAt) }} days left).
                        </template>
                        <template
                            v-else-if="
                                billing.status === 'canceling' && billing.endsAt
                            "
                        >
                            Your subscription has been canceled and ends on
                            {{ formatDate(billing.endsAt) }}.
                        </template>
                        <template v-else-if="billing.status === 'past_due'">
                            Your last payment failed. Update your payment method
                            to keep your plan.
                        </template>
                        <template v-else-if="billing.subscribed">
                            Billed {{ billing.interval }}.
                        </template>
                        <template v-else>
                            You're on the free plan. Upgrade any time.
                        </template>
                    </CardDescription>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="billing.status === 'canceling'"
                        @click="
                            router.post(
                                resume().url,
                                {},
                                { preserveScroll: true },
                            )
                        "
                    >
                        Resume subscription
                    </Button>
                    <Button
                        v-else-if="billing.subscribed"
                        variant="outline"
                        @click="cancelOpen = true"
                    >
                        Cancel plan
                    </Button>
                    <Button
                        variant="outline"
                        :disabled="!stripeConfigured"
                        as="a"
                        :href="portal().url"
                    >
                        <ExternalLink class="size-4" /> Billing portal
                    </Button>
                </div>
            </CardHeader>
            <CardContent v-if="billing.paymentMethod">
                <div
                    class="inline-flex items-center gap-3 rounded-lg border px-4 py-3 text-sm"
                >
                    <CreditCard class="size-4 text-muted-foreground" />
                    <span class="capitalize">{{
                        billing.paymentMethod.brand
                    }}</span>
                    <span class="text-muted-foreground tabular-nums"
                        >•••• {{ billing.paymentMethod.lastFour }}</span
                    >
                </div>
            </CardContent>
        </Card>

        <!-- Plans -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-lg font-semibold">Plans</h2>
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
                            plan.highlighted && !isCurrent(plan)
                                ? 'default'
                                : 'outline'
                        "
                        :disabled="
                            isCurrent(plan) ||
                            !plan.purchasable ||
                            !stripeConfigured ||
                            processingPlan !== null
                        "
                        :data-test="`choose-${plan.key}`"
                        @click="choose(plan)"
                    >
                        {{
                            processingPlan === plan.key
                                ? 'Redirecting…'
                                : ctaLabel(plan)
                        }}
                    </Button>
                </template>
            </PlanCard>
        </div>

        <!-- Invoices -->
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Receipt class="size-4" /> Invoices
                </CardTitle>
            </CardHeader>
            <CardContent>
                <Deferred data="invoices">
                    <template #fallback>
                        <div class="space-y-3">
                            <Skeleton
                                v-for="i in 3"
                                :key="i"
                                class="h-8 w-full"
                            />
                        </div>
                    </template>
                    <table
                        v-if="invoices?.length"
                        class="w-full text-left text-sm"
                    >
                        <thead class="text-xs text-muted-foreground">
                            <tr class="border-b">
                                <th class="pb-2 font-medium">Invoice</th>
                                <th class="pb-2 font-medium">Date</th>
                                <th class="pb-2 font-medium">Status</th>
                                <th class="pb-2 text-right font-medium">
                                    Total
                                </th>
                                <th class="pb-2">
                                    <span class="sr-only">Link</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="invoice in invoices" :key="invoice.id">
                                <td class="py-3 font-medium">
                                    {{ invoice.number ?? invoice.id }}
                                </td>
                                <td class="py-3 text-muted-foreground">
                                    {{ formatDate(invoice.date) }}
                                </td>
                                <td class="py-3 capitalize">
                                    {{ invoice.status }}
                                </td>
                                <td class="py-3 text-right tabular-nums">
                                    {{ invoice.total }}
                                </td>
                                <td class="py-3 text-right">
                                    <a
                                        v-if="invoice.url"
                                        :href="invoice.url"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-muted-foreground hover:text-foreground"
                                        :aria-label="`Open invoice ${invoice.number ?? invoice.id}`"
                                    >
                                        <FileText class="inline size-4" />
                                    </a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p
                        v-else
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        No invoices yet.
                    </p>
                </Deferred>
            </CardContent>
        </Card>
    </div>

    <ConfirmDialog
        v-model:open="cancelOpen"
        title="Cancel your subscription?"
        description="You'll keep access until the end of the current billing period, then move to the Free plan and its limits."
        confirm-label="Cancel subscription"
        @confirm="performCancel"
    />
</template>
