<script setup lang="ts">
import {
    CreditCard,
    FolderPlus,
    FolderX,
    Pencil,
    Settings2,
    UserMinus,
    UserPlus,
    Activity as ActivityIcon,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { formatDate, timeAgo } from '@/lib/format';
import type { ActivityItem } from '@/types';

defineProps<{
    items: ActivityItem[];
}>();

function iconFor(event: string): LucideIcon {
    if (event === 'project.created') return FolderPlus;
    if (event === 'project.deleted') return FolderX;
    if (event === 'project.updated') return Pencil;
    if (event.startsWith('billing.')) return CreditCard;
    if (event === 'member.removed' || event === 'member.left') return UserMinus;
    if (event.startsWith('member.')) return UserPlus;
    if (event.startsWith('organization.')) return Settings2;
    return ActivityIcon;
}
</script>

<template>
    <ul v-if="items.length" class="space-y-4">
        <li v-for="item in items" :key="item.id" class="flex gap-3">
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-full border bg-muted/50"
            >
                <component
                    :is="iconFor(item.event)"
                    class="size-3.5 text-muted-foreground"
                />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm leading-snug">
                    <span class="font-medium">{{
                        item.causer?.name ?? 'System'
                    }}</span>
                    <span class="text-muted-foreground">
                        &middot; {{ item.description }}</span
                    >
                </p>
                <time
                    class="text-xs text-muted-foreground"
                    :datetime="item.createdAt"
                    :title="
                        formatDate(item.createdAt, {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                        })
                    "
                >
                    {{ timeAgo(item.createdAt) }}
                </time>
            </div>
        </li>
    </ul>
    <p v-else class="py-6 text-center text-sm text-muted-foreground">
        No activity yet.
    </p>
</template>
