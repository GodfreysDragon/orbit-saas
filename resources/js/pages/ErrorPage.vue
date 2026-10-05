<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

const props = defineProps<{
    status: number;
    message?: string | null;
}>();

const copy = computed(() => {
    switch (props.status) {
        case 403:
            return {
                title: 'Access denied',
                description:
                    "Your role in this organization doesn't allow this action.",
            };
        case 404:
            return {
                title: 'Page not found',
                description:
                    "This page doesn't exist, or it belongs to another organization.",
            };
        case 410:
            return {
                title: 'Link expired',
                description: 'This link is no longer valid.',
            };
        default:
            return {
                title: 'Something went wrong',
                description: 'Please try again in a moment.',
            };
    }
});
</script>

<template>
    <Head :title="copy.title" />

    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 text-center"
    >
        <p class="text-sm font-semibold text-primary tabular-nums">
            {{ status }}
        </p>
        <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight">
                {{ copy.title }}
            </h1>
            <p class="max-w-md text-muted-foreground">
                {{ message || copy.description }}
            </p>
        </div>
        <Button as-child>
            <Link :href="dashboard()">Back to dashboard</Link>
        </Button>
    </div>
</template>
