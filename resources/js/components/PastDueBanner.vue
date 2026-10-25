<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CreditCard } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { portal } from '@/routes/billing';

// Shown on every page while a renewal payment is failing (Stripe retries it).
const page = usePage();
const { can } = usePermissions();

const organization = computed(() => page.props.auth.organization);
const visible = computed(
    () => organization.value?.billingStatus === 'past_due',
);
</script>

<template>
    <div
        v-if="visible"
        role="alert"
        class="flex flex-wrap items-center gap-3 border-b border-amber-300 bg-amber-50 px-4 py-2.5 text-sm text-amber-900 md:px-6 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-100"
        data-test="past-due-banner"
    >
        <CreditCard class="size-4 shrink-0" />
        <p class="min-w-0 flex-1">
            <span class="font-medium">Your last payment failed.</span>
            <template v-if="can('billing.manage')">
                Update the payment method to keep the
                {{ organization?.plan.name }} plan.
            </template>
            <template v-else>
                Ask the organization's owner to update the payment method.
            </template>
        </p>
        <Button
            v-if="can('billing.manage')"
            size="sm"
            variant="outline"
            class="border-amber-400 bg-white/60 dark:border-amber-700 dark:bg-transparent"
            as="a"
            :href="portal().url"
        >
            Update payment method
        </Button>
    </div>
</template>
