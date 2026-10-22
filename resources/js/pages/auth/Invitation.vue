<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { MailOpen } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { home, login, register } from '@/routes';
import type { Role } from '@/types';

defineProps<{
    invitation: {
        token: string;
        email: string;
        role: Role;
        organization: { name: string };
        invitedBy: string | null;
        expiresAt: string;
        expired: boolean;
    };
    accountExists: boolean;
}>();

defineOptions({
    layout: {
        title: "You're invited",
    },
});
</script>

<template>
    <Head title="Invitation" />

    <div class="flex flex-col gap-6 text-center">
        <div
            class="mx-auto flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
        >
            <MailOpen class="size-5" />
        </div>

        <div class="space-y-2">
            <p class="text-lg font-medium">
                Join {{ invitation.organization.name }}
            </p>
            <p class="text-sm text-muted-foreground">
                {{ invitation.invitedBy ?? 'Someone' }} invited
                <strong class="text-foreground">{{ invitation.email }}</strong>
                to join as
                <strong class="text-foreground">{{
                    invitation.role.label
                }}</strong
                >. {{ invitation.role.description }}
            </p>
        </div>

        <template v-if="invitation.expired">
            <p
                class="rounded-md bg-amber-500/10 p-3 text-sm text-amber-800 dark:text-amber-300"
            >
                This invitation has expired. Ask
                {{ invitation.invitedBy ?? 'the organization' }} for a new one.
            </p>
            <Button variant="outline" as-child>
                <Link :href="home()">Go to the home page</Link>
            </Button>
        </template>

        <template v-else>
            <div class="flex flex-col gap-2">
                <Button
                    :variant="accountExists ? 'outline' : 'default'"
                    :class="accountExists ? 'order-2' : ''"
                    as-child
                >
                    <Link
                        :href="
                            register({
                                query: { invitation: invitation.token },
                            })
                        "
                        data-test="invitation-register"
                        >Create an account</Link
                    >
                </Button>
                <Button
                    :variant="accountExists ? 'default' : 'outline'"
                    as-child
                >
                    <Link :href="login()" data-test="invitation-login"
                        >Log in to accept</Link
                    >
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Expires {{ formatDate(invitation.expiresAt) }}
            </p>
        </template>
    </div>
</template>
