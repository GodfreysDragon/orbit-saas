<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{
    paginator: Paginated<unknown>;
}>();
</script>

<template>
    <nav
        v-if="paginator.last_page > 1"
        class="flex items-center justify-between gap-4 text-sm"
        aria-label="Pagination"
    >
        <p class="text-muted-foreground">
            Showing
            <span class="font-medium text-foreground">{{
                paginator.from
            }}</span>
            to
            <span class="font-medium text-foreground">{{ paginator.to }}</span>
            of
            <span class="font-medium text-foreground">{{
                paginator.total
            }}</span>
        </p>
        <div class="flex gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="!paginator.links[0]?.url"
                as-child
            >
                <component
                    :is="paginator.links[0]?.url ? Link : 'span'"
                    :href="paginator.links[0]?.url ?? undefined"
                    preserve-scroll
                >
                    <ChevronLeft class="size-4" /> Previous
                </component>
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!paginator.links.at(-1)?.url"
                as-child
            >
                <component
                    :is="paginator.links.at(-1)?.url ? Link : 'span'"
                    :href="paginator.links.at(-1)?.url ?? undefined"
                    preserve-scroll
                >
                    Next <ChevronRight class="size-4" />
                </component>
            </Button>
        </div>
    </nav>
</template>
