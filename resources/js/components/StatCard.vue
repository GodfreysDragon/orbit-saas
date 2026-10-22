<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    label: string;
    value: string | number;
    hint?: string;
    icon?: LucideIcon;
    // Renders the card as a link (e.g. to a section further down the page).
    href?: string;
}>();
</script>

<template>
    <component
        :is="href ? 'a' : 'div'"
        :href="href"
        class="rounded-xl border bg-card p-5 shadow-xs"
        :class="
            href &&
            'block cursor-pointer transition-colors outline-none hover:border-primary/50 hover:bg-primary/5 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50'
        "
    >
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
    </component>
</template>
