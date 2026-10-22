<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import OrganizationController from '@/actions/App/Http/Controllers/Organizations/OrganizationController';
import InputError from '@/components/InputError.vue';
import PageGuide from '@/components/PageGuide.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create } from '@/routes/organizations';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'New organization', href: create() }],
    },
});
</script>

<template>
    <Head title="New organization" />

    <div
        class="flex flex-1 flex-col items-center justify-start gap-6 p-4 md:p-10"
    >
        <PageGuide
            id="organization-create"
            class="w-full max-w-lg"
            summary="Organizations are this app's tenants. Each has its own members, projects, billing and activity. You become the Owner of any organization you create, and you can switch between organizations from the sidebar."
            :details="[
                'The `CreateOrganization` action creates the organization, your membership and your Owner role in one transaction.',
                'New organizations start a card-less 14-day trial of the Pro plan.',
            ]"
        />
        <Card class="w-full max-w-lg">
            <CardHeader>
                <div
                    class="mb-2 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary"
                >
                    <Building2 class="size-5" />
                </div>
                <CardTitle>Create an organization</CardTitle>
                <CardDescription>
                    Organizations have their own members, projects and billing.
                    New organizations start with a free trial.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="OrganizationController.store.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Organization name</Label>
                        <Input
                            id="name"
                            name="name"
                            placeholder="Acme Inc"
                            required
                            autofocus
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="processing"
                        data-test="create-organization"
                    >
                        Create organization
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
