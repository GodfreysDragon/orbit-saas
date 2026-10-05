<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';

defineProps<{
    icon: LucideIcon;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-14 text-center"
    >
        <div
            class="mb-4 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
            <component :is="icon" class="size-5" />
        </div>
        <h3 class="text-base font-semibold">{{ title }}</h3>
        <p
            v-if="description"
            class="mt-1 max-w-sm text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-6">
            <slot />
        </div>
    </div>
</template>
