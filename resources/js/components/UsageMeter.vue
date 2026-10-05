<script setup lang="ts">
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import type { Usage } from '@/types';

const props = defineProps<{
    label: string;
    usage: Usage;
}>();

const percent = computed(() =>
    props.usage.limit === null
        ? 0
        : Math.min(
              100,
              Math.round((props.usage.used / props.usage.limit) * 100),
          ),
);

// "Near limit" is conveyed with an icon + text, never by color alone.
const state = computed(() => {
    if (props.usage.limit === null) return 'unlimited';
    if (props.usage.used >= props.usage.limit) return 'full';
    if (percent.value >= 80) return 'near';
    return 'ok';
});
</script>

<template>
    <div class="space-y-2">
        <div class="flex items-baseline justify-between text-sm">
            <span class="font-medium">{{ label }}</span>
            <span class="text-muted-foreground tabular-nums">
                {{ usage.used }}
                <template v-if="usage.limit !== null">
                    / {{ usage.limit }}
                </template>
                <template v-else>&middot; Unlimited</template>
            </span>
        </div>
        <div
            class="h-2 overflow-hidden rounded-full bg-muted"
            role="meter"
            :aria-label="label"
            :aria-valuenow="usage.used"
            :aria-valuemin="0"
            :aria-valuemax="usage.limit ?? undefined"
        >
            <div
                class="h-full rounded-full transition-[width] duration-500"
                :class="
                    state === 'full' || state === 'near'
                        ? 'bg-amber-500'
                        : 'bg-primary'
                "
                :style="{ width: `${percent}%` }"
            />
        </div>
        <p
            v-if="state === 'full' || state === 'near'"
            class="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400"
        >
            <TriangleAlert class="size-3.5" aria-hidden="true" />
            {{ state === 'full' ? 'Limit reached' : 'Approaching limit' }}
        </p>
    </div>
</template>
