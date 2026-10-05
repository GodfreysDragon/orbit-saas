<script setup lang="ts">
import { computed, ref } from 'vue';
import { formatDate } from '@/lib/format';

type Point = { date: string; count: number };

const props = defineProps<{
    points: Point[];
}>();

// Single series: one hue (the brand primary), no legend, recessive axis,
// a per-bar tooltip and a screen-reader table as the accessible view.
const max = computed(() =>
    Math.max(1, ...props.points.map((point) => point.count)),
);
const total = computed(() =>
    props.points.reduce((sum, point) => sum + point.count, 0),
);
const hovered = ref<number | null>(null);

const label = (point: Point) =>
    formatDate(point.date, { month: 'short', day: 'numeric' });
</script>

<template>
    <div>
        <div class="mb-4 flex items-baseline gap-2">
            <span class="text-2xl font-semibold tabular-nums">{{ total }}</span>
            <span class="text-sm text-muted-foreground">
                events in the last 14 days
            </span>
        </div>

        <div class="relative">
            <div
                class="pointer-events-none absolute inset-x-0 top-0 border-t border-dashed border-border"
                aria-hidden="true"
            />
            <span
                class="absolute -top-2.5 right-0 bg-card pl-1 text-[10px] text-muted-foreground tabular-nums"
                aria-hidden="true"
                >{{ max }}</span
            >

            <div
                class="flex h-36 items-end gap-0.5 border-b border-border"
                aria-hidden="true"
                @mouseleave="hovered = null"
            >
                <div
                    v-for="(point, index) in points"
                    :key="point.date"
                    class="group relative flex h-full flex-1 items-end justify-center"
                    @mouseenter="hovered = index"
                >
                    <div
                        class="w-full max-w-6 rounded-t-[4px] bg-chart-1 transition-opacity"
                        :class="
                            hovered !== null && hovered !== index
                                ? 'opacity-40'
                                : ''
                        "
                        :style="{
                            height: `${(point.count / max) * 100}%`,
                            minHeight: point.count > 0 ? '2px' : '0',
                        }"
                    />
                    <div
                        v-if="hovered === index"
                        class="absolute bottom-full z-10 mb-2 rounded-md border bg-popover px-2 py-1 text-xs whitespace-nowrap text-popover-foreground shadow-md"
                    >
                        <span class="font-medium tabular-nums">{{
                            point.count
                        }}</span>
                        <span class="text-muted-foreground">
                            on {{ label(point) }}</span
                        >
                    </div>
                </div>
            </div>
            <div
                class="mt-2 flex justify-between text-[10px] text-muted-foreground"
                aria-hidden="true"
            >
                <span>{{ points[0] ? label(points[0]) : '' }}</span>
                <span>Today</span>
            </div>
        </div>

        <table class="sr-only">
            <caption>
                Activity per day, last 14 days
            </caption>
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Events</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="point in points" :key="point.date">
                    <td>{{ label(point) }}</td>
                    <td>{{ point.count }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
