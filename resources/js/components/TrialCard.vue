<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { daysUntil } from '@/lib/format';
import { index as billing } from '@/routes/billing';

const page = usePage();
const { can } = usePermissions();

const organization = computed(() => page.props.auth.organization);
const visible = computed(
    () =>
        organization.value?.billingStatus === 'trialing' &&
        organization.value.trialEndsAt !== null,
);
const days = computed(() =>
    organization.value?.trialEndsAt
        ? daysUntil(organization.value.trialEndsAt)
        : 0,
);
</script>

<template>
    <div
        v-if="visible"
        class="rounded-lg border border-primary/20 bg-primary/5 p-3 text-sm group-data-[collapsible=icon]:hidden"
    >
        <div class="flex items-center gap-2 font-medium">
            <Sparkles class="size-4 text-primary" />
            {{ organization?.plan.name }} trial
        </div>
        <p class="mt-1 text-xs text-muted-foreground">
            {{ days }} {{ days === 1 ? 'day' : 'days' }} left. Pick a plan to
            keep your limits.
        </p>
        <Button
            v-if="can('billing.manage')"
            size="sm"
            class="mt-3 w-full"
            as-child
        >
            <Link :href="billing()">Choose a plan</Link>
        </Button>
    </div>
</template>
