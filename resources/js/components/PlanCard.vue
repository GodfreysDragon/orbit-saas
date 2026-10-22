<script setup lang="ts">
import { Check } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { Plan } from '@/types';

defineProps<{
    plan: Plan;
    interval: 'monthly' | 'yearly';
}>();
</script>

<template>
    <Card
        class="relative flex flex-col"
        :class="
            plan.highlighted ? 'border-primary shadow-lg shadow-primary/10' : ''
        "
    >
        <Badge v-if="plan.highlighted" class="absolute -top-2.5 left-6"
            >Most popular</Badge
        >
        <CardHeader>
            <CardTitle>{{ plan.name }}</CardTitle>
            <CardDescription>{{ plan.description }}</CardDescription>
        </CardHeader>
        <CardContent class="flex-1 space-y-6">
            <div class="flex items-baseline gap-1">
                <span class="text-4xl font-semibold tracking-tight">
                    ${{ plan.prices[interval] }}
                </span>
                <span class="text-sm text-muted-foreground">
                    / {{ interval === 'monthly' ? 'month' : 'year' }}
                </span>
            </div>
            <ul class="space-y-2.5 text-sm">
                <li
                    v-for="feature in plan.features"
                    :key="feature"
                    class="flex items-center gap-2"
                >
                    <Check class="size-4 shrink-0 text-primary" />
                    {{ feature }}
                </li>
            </ul>
        </CardContent>
        <CardFooter>
            <slot name="footer" />
        </CardFooter>
    </Card>
</template>
