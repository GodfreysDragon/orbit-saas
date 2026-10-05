<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ActivityFeed from '@/components/ActivityFeed.vue';
import Heading from '@/components/Heading.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/activity';
import type { ActivityItem, Paginated } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Activity', href: index() }],
    },
});

defineProps<{
    activities: Paginated<ActivityItem>;
}>();
</script>

<template>
    <Head title="Activity" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Activity log"
            description="An audit trail of everything that happens in this organization."
        />

        <Card>
            <CardContent class="pt-6">
                <ActivityFeed :items="activities.data" />
            </CardContent>
        </Card>

        <PaginationLinks :paginator="activities" />
    </div>
</template>
