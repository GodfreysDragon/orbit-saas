<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import OrganizationController from '@/actions/App/Http/Controllers/Organizations/OrganizationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PageGuide from '@/components/PageGuide.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import { edit } from '@/routes/organization';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Organization settings', href: edit() }],
    },
});

defineProps<{
    organization: {
        id: number;
        name: string;
        slug: string;
        personal: boolean;
        created_at: string;
    };
    owner: { id: number; name: string; email: string };
    can: { update: boolean; delete: boolean; deleteReason: string | null };
}>();
</script>

<template>
    <Head title="Organization settings" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Organization settings"
            description="General information about this organization."
        />

        <PageGuide
            id="organization-settings"
            summary="Rename the organization, or delete it along with all of its projects, members and history. Your personal workspace can't be deleted."
            :details="[
                'Renaming needs the `organization.update` permission. Deleting is Owner-only.',
                'Deleting runs the `DeleteOrganization` action, and foreign keys cascade so no orphaned tenant data is left behind.',
                'Members who had this organization selected are moved to another one they belong to.',
            ]"
        />

        <div class="max-w-2xl space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>General</CardTitle>
                    <CardDescription>
                        Owned by {{ owner.name }} ({{ owner.email }}) &middot;
                        created {{ formatDate(organization.created_at) }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="OrganizationController.update.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input
                                id="name"
                                name="name"
                                :default-value="organization.name"
                                :disabled="!can.update"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="slug">Slug</Label>
                            <Input
                                id="slug"
                                :default-value="organization.slug"
                                disabled
                            />
                        </div>
                        <Button
                            v-if="can.update"
                            type="submit"
                            :disabled="processing"
                            >Save</Button
                        >
                        <p v-else class="text-sm text-muted-foreground">
                            Only owners and admins can change these settings.
                        </p>
                    </Form>
                </CardContent>
            </Card>

            <Card
                v-if="can.delete"
                class="border-red-200 dark:border-red-900/50"
            >
                <CardHeader>
                    <CardTitle class="text-red-600 dark:text-red-400"
                        >Delete organization</CardTitle
                    >
                    <CardDescription>
                        Permanently delete this organization, its projects and
                        activity. Any active subscription is canceled
                        immediately.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button variant="destructive"
                                >Delete organization</Button
                            >
                        </DialogTrigger>
                        <DialogContent>
                            <Form
                                v-bind="OrganizationController.destroy.form()"
                                class="space-y-6"
                                v-slot="{ errors, processing }"
                            >
                                <DialogHeader>
                                    <DialogTitle
                                        >Delete
                                        {{ organization.name }}?</DialogTitle
                                    >
                                    <DialogDescription>
                                        This cannot be undone. Enter your
                                        password to confirm.
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="grid gap-2">
                                    <Label for="password" class="sr-only"
                                        >Password</Label
                                    >
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        placeholder="Password"
                                    />
                                    <InputError :message="errors.password" />
                                </div>
                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button variant="secondary"
                                            >Cancel</Button
                                        >
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                        >Delete organization</Button
                                    >
                                </DialogFooter>
                            </Form>
                        </DialogContent>
                    </Dialog>
                </CardContent>
            </Card>
            <p
                v-else-if="can.deleteReason"
                class="text-sm text-muted-foreground"
            >
                {{ can.deleteReason }}
            </p>
        </div>
    </div>
</template>
