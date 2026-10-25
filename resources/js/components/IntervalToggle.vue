<script setup lang="ts">
const interval = defineModel<'monthly' | 'yearly'>({ required: true });
</script>

<template>
    <div
        class="inline-flex rounded-lg border p-1 text-sm"
        role="group"
        aria-label="Billing interval"
    >
        <button
            v-for="option in ['monthly', 'yearly'] as const"
            :key="option"
            type="button"
            class="rounded-md px-3 py-1.5 transition-colors"
            :class="
                interval === option
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:text-foreground'
            "
            :aria-pressed="interval === option"
            :data-test="`interval-${option}`"
            @click="interval = option"
        >
            {{ option === 'monthly' ? 'Monthly' : 'Yearly' }}
            <span v-if="option === 'yearly'" class="ml-1 text-xs opacity-80"
                >· 2 months free</span
            >
        </button>
    </div>
</template>
