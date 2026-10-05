<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
}>();
</script>

<template>
    <div class="rounded-xl border bg-card p-5 shadow-xs">
        <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
            <component
                :is="icon"
                v-if="icon"
                class="size-4 text-muted-foreground"
                aria-hidden="true"
            />
        </div>
        <p class="mt-2 text-3xl font-semibold tracking-tight tabular-nums">
            {{ value }}
        </p>
        <p
            v-if="hint || $slots.hint"
            class="mt-1 text-xs text-muted-foreground"
        >
            <slot name="hint">{{ hint }}</slot>
        </p>
    </div>
</template>
