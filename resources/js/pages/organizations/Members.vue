<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Clock, Crown, LogOut, Mail, UserPlus, X } from '@lucide/vue';
import { ref } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import UsageMeter from '@/components/UsageMeter.vue';
import { initials, timeAgo } from '@/lib/format';
import { index as billingIndex } from '@/routes/billing';
import { destroy, index, update } from '@/routes/members';
import {
    destroy as revokeInvitation,
    store as storeInvitation,
} from '@/routes/organization/invitations';
import type { Role } from '@/types';

type Member = {
    id: number;
    name: string;
    email: string;
    role: Role | null;
    isOwner: boolean;
    isCurrentUser: boolean;
    joinedAt: string | null;
    can: { update: boolean; remove: boolean };
};

type Invitation = {
    id: number;
    email: string;
    role: Role;
    invitedBy: string | null;
    expiresAt: string;
    expired: boolean;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: index() }],
    },
});

const props = defineProps<{
    members: Member[];
    invitations: Invitation[];
    roles: Role[];
    seats: { used: number; limit: number | null; plan: string };
    can: { invite: boolean; invitePossible: boolean; manageBilling: boolean };
}>();

const inviteForm = useForm({
    email: '',
    role: 'member',
});

function invite() {
    inviteForm.post(storeInvitation().url, {
        preserveScroll: true,
        onSuccess: () => inviteForm.reset('email'),
    });
}

function changeRole(member: Member, role: unknown) {
    if (typeof role !== 'string' || role === member.role?.value) return;

    router.patch(update(member.id).url, { role }, { preserveScroll: true });
}

function revoke(invitation: Invitation) {
    router.delete(revokeInvitation(invitation.id).url, {
        preserveScroll: true,
    });
}

const removing = ref<Member | null>(null);
const removeOpen = ref(false);
const removeProcessing = ref(false);

function confirmRemove(member: Member) {
    removing.value = member;
    removeOpen.value = true;
}

function performRemove() {
    if (!removing.value) return;

    router.delete(destroy(removing.value.id).url, {
        preserveScroll: true,
        onStart: () => (removeProcessing.value = true),
        onFinish: () => {
            removeProcessing.value = false;
            removeOpen.value = false;
        },
    });
}

const roleById = (value: string) =>
    props.roles.find((role) => role.value === value);
</script>

<template>
    <Head title="Members" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Members"
            description="Manage who has access to this organization and what they can do."
        />

        <div class="grid gap-6 lg:grid-cols-3">
            <Card v-if="can.invite" class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <UserPlus class="size-4" /> Invite a teammate
                    </CardTitle>
                    <CardDescription>
                        They'll get an email with a link to join. Invitations
                        expire after 7 days.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        class="flex flex-col gap-3 sm:flex-row sm:items-start"
                        @submit.prevent="invite"
                    >
                        <div class="grid flex-1 gap-2">
                            <Label for="invite-email" class="sr-only"
                                >Email</Label
                            >
                            <Input
                                id="invite-email"
                                v-model="inviteForm.email"
                                type="email"
                                placeholder="teammate@company.com"
                                required
                                :disabled="!can.invitePossible"
                            />
                            <InputError :message="inviteForm.errors.email" />
                        </div>
                        <div class="grid gap-2 sm:w-40">
                            <Label for="invite-role" class="sr-only"
                                >Role</Label
                            >
                            <Select
                                v-model="inviteForm.role"
                                :disabled="!can.invitePossible"
                            >
                                <SelectTrigger id="invite-role" class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="role in roles"
                                        :key="role.value"
                                        :value="role.value"
                                    >
                                        {{ role.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="inviteForm.errors.role" />
                        </div>
                        <Button
                            type="submit"
                            :disabled="
                                inviteForm.processing || !can.invitePossible
                            "
                            data-test="send-invitation"
                        >
                            <Mail class="size-4" /> Send invite
                        </Button>
                    </form>
                    <p
                        v-if="inviteForm.role"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        <span class="font-medium text-foreground">{{
                            roleById(inviteForm.role)?.label
                        }}</span>
                        — {{ roleById(inviteForm.role)?.description }}
                    </p>
                </CardContent>
            </Card>

            <Card :class="can.invite ? '' : 'lg:col-span-3'">
                <CardHeader>
                    <CardTitle>Seats</CardTitle>
                    <CardDescription
                        >Members and pending invitations on the
                        {{ seats.plan }} plan</CardDescription
                    >
                </CardHeader>
                <CardContent class="space-y-4">
                    <UsageMeter label="Seats used" :usage="seats" />
                    <Button
                        v-if="!can.invitePossible && can.manageBilling"
                        variant="outline"
                        size="sm"
                        class="w-full"
                        as-child
                    >
                        <Link :href="billingIndex()"
                            >Upgrade for more seats</Link
                        >
                    </Button>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Team</CardTitle>
                <CardDescription
                    >{{ members.length }}
                    {{
                        members.length === 1 ? 'member' : 'members'
                    }}</CardDescription
                >
            </CardHeader>
            <CardContent class="px-0">
                <ul class="divide-y border-t">
                    <li
                        v-for="member in members"
                        :key="member.id"
                        class="flex flex-wrap items-center gap-4 px-6 py-4"
                        data-test="member-row"
                    >
                        <Avatar class="size-9">
                            <AvatarFallback class="text-xs">{{
                                initials(member.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <div class="min-w-0 flex-1">
                            <p
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <span class="truncate">{{ member.name }}</span>
                                <Badge
                                    v-if="member.isCurrentUser"
                                    variant="secondary"
                                    >You</Badge
                                >
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ member.email }}
                                <template v-if="member.joinedAt">
                                    &middot; joined
                                    {{ timeAgo(member.joinedAt) }}
                                </template>
                            </p>
                        </div>

                        <Badge
                            v-if="member.isOwner"
                            variant="outline"
                            class="gap-1"
                        >
                            <Crown class="size-3 text-amber-500" /> Owner
                        </Badge>
                        <Select
                            v-else-if="member.can.update"
                            :model-value="member.role?.value"
                            @update:model-value="changeRole(member, $event)"
                        >
                            <SelectTrigger
                                size="sm"
                                class="w-32"
                                :aria-label="`Role for ${member.name}`"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="role in roles"
                                    :key="role.value"
                                    :value="role.value"
                                >
                                    {{ role.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Badge v-else variant="outline">{{
                            member.role?.label ?? 'No role'
                        }}</Badge>

                        <Button
                            v-if="member.can.remove"
                            variant="ghost"
                            size="sm"
                            class="text-muted-foreground hover:text-destructive"
                            @click="confirmRemove(member)"
                        >
                            <template v-if="member.isCurrentUser">
                                <LogOut class="size-4" /> Leave
                            </template>
                            <template v-else>Remove</template>
                        </Button>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card v-if="can.invite && invitations.length">
            <CardHeader>
                <CardTitle>Pending invitations</CardTitle>
                <CardDescription
                    >Invitations count toward your seat limit until accepted or
                    revoked.</CardDescription
                >
            </CardHeader>
            <CardContent class="px-0">
                <ul class="divide-y border-t">
                    <li
                        v-for="invitation in invitations"
                        :key="invitation.id"
                        class="flex flex-wrap items-center gap-4 px-6 py-4"
                    >
                        <div
                            class="flex size-9 items-center justify-center rounded-full border border-dashed"
                        >
                            <Mail class="size-4 text-muted-foreground" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ invitation.email }}
                            </p>
                            <p
                                class="flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <Clock class="size-3" />
                                <template v-if="invitation.expired"
                                    >Expired</template
                                >
                                <template v-else
                                    >Expires
                                    {{
                                        timeAgo(invitation.expiresAt)
                                    }}</template
                                >
                                <template v-if="invitation.invitedBy">
                                    &middot; invited by
                                    {{ invitation.invitedBy }}
                                </template>
                            </p>
                        </div>
                        <Badge variant="outline">{{
                            invitation.role.label
                        }}</Badge>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-muted-foreground"
                            @click="revoke(invitation)"
                        >
                            <X class="size-4" /> Revoke
                        </Button>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>

    <ConfirmDialog
        v-model:open="removeOpen"
        :title="
            removing?.isCurrentUser
                ? 'Leave this organization?'
                : `Remove ${removing?.name}?`
        "
        :description="
            removing?.isCurrentUser
                ? 'You will lose access to all of its projects. You can only rejoin by invitation.'
                : 'They will immediately lose access to this organization and its projects.'
        "
        :confirm-label="
            removing?.isCurrentUser ? 'Leave organization' : 'Remove member'
        "
        :processing="removeProcessing"
        @confirm="performRemove"
    />
</template>
