<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import PageGuide from '@/components/PageGuide.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Appearance settings" />

    <h1 class="sr-only">Appearance settings</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Appearance settings"
            description="Update the appearance settings for your account"
        />
        <PageGuide
            id="settings-appearance"
            summary="Choose light, dark or match your system. Every page, including this demo's landing page, supports both themes."
            :details="[
                'Your choice is saved in a cookie as well as the browser, so the server renders the right theme without a flash of the wrong one.',
            ]"
        />
        <AppearanceTabs />
    </div>
</template>
