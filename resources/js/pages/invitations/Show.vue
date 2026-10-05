<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { MailOpen } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { accept, decline } from '@/routes/invitations';
import type { Role } from '@/types';

const props = defineProps<{
    invitation: {
        token: string;
        email: string;
        role: Role;
        organization: { name: string };
        invitedBy: string | null;
        expiresAt: string;
        expired: boolean;
    };
    emailMatches: boolean;
    alreadyMember: boolean;
}>();

const page = usePage();
const processing = ref(false);

const problem = computed(() => {
    if (props.alreadyMember)
        return `You're already a member of ${props.invitation.organization.name}.`;
    if (props.invitation.expired)
        return 'This invitation has expired. Ask for a new one.';
    if (!props.emailMatches)
        return `This invitation was sent to ${props.invitation.email}, but you're signed in as ${page.props.auth.user.email}.`;
    return null;
});

const visitOptions = {
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
};
</script>

<template>
    <Head title="Invitation" />

    <div class="flex flex-1 items-start justify-center p-4 md:p-10">
        <Card class="w-full max-w-md text-center">
            <CardHeader class="items-center">
                <div
                    class="mx-auto mb-2 flex size-12 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <MailOpen class="size-5" />
                </div>
                <CardTitle class="text-xl">
                    Join {{ invitation.organization.name }}
                </CardTitle>
                <CardDescription>
                    {{ invitation.invitedBy ?? 'Someone' }} invited you to join
                    as <strong>{{ invitation.role.label }}</strong
                    >.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-2 text-sm text-muted-foreground">
                <p>{{ invitation.role.description }}</p>
                <p v-if="!invitation.expired">
                    Expires {{ formatDate(invitation.expiresAt) }}
                </p>
                <p
                    v-if="problem"
                    class="rounded-md bg-amber-500/10 p-3 text-amber-800 dark:text-amber-300"
                >
                    {{ problem }}
                </p>
            </CardContent>
            <CardFooter class="flex justify-center gap-2">
                <template v-if="!problem">
                    <Button
                        variant="outline"
                        :disabled="processing"
                        @click="
                            router.delete(
                                decline(invitation.token).url,
                                visitOptions,
                            )
                        "
                    >
                        Decline
                    </Button>
                    <Button
                        :disabled="processing"
                        data-test="accept-invitation"
                        @click="
                            router.post(
                                accept(invitation.token).url,
                                {},
                                visitOptions,
                            )
                        "
                    >
                        Accept invitation
                    </Button>
                </template>
                <Button v-else variant="outline" as-child>
                    <Link :href="dashboard()">Go to dashboard</Link>
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>
