<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    FolderKanban,
    Lock,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import PageGuide from '@/components/PageGuide.vue';
import PaginationLinks from '@/components/PaginationLinks.vue';
import ProjectFormDialog from '@/components/ProjectFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { timeAgo } from '@/lib/format';
import { destroy, index } from '@/routes/projects';
import type { Paginated, Project, Usage } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: index() }],
    },
});

const props = defineProps<{
    projects: Paginated<Project>;
    filters: { search: string; status: string | null };
    statuses: { value: string; label: string }[];
    usage: Usage;
    can: {
        create: boolean;
        createReason: string | null;
        update: boolean;
        delete: boolean;
    };
}>();

const search = ref(props.filters.search);
const status = ref(props.filters.status ?? '');

const reload = useDebounceFn(() => {
    router.get(
        index().url,
        {
            search: search.value || undefined,
            status: status.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}, 300);

watch([search, status], reload);

const formOpen = ref(false);
const editing = ref<Project | null>(null);

function openCreate() {
    editing.value = null;
    formOpen.value = true;
}

function openEdit(project: Project) {
    editing.value = project;
    formOpen.value = true;
}

const deleting = ref<Project | null>(null);
const deleteOpen = ref(false);
const deleteProcessing = ref(false);

function confirmDelete(project: Project) {
    deleting.value = project;
    deleteOpen.value = true;
}

function performDelete() {
    if (!deleting.value) return;

    router.delete(destroy({ project: deleting.value.id }).url, {
        preserveScroll: true,
        onStart: () => (deleteProcessing.value = true),
        onFinish: () => {
            deleteProcessing.value = false;
            deleteOpen.value = false;
        },
    });
}

const statusDot: Record<Project['status'], string> = {
    active: 'bg-emerald-500',
    paused: 'bg-amber-500',
    archived: 'bg-muted-foreground/50',
};
</script>

<template>
    <Head title="Projects" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Projects"
                :description="
                    usage.limit === null
                        ? `${usage.used} projects`
                        : `${usage.used} of ${usage.limit} projects used on your plan`
                "
            />

            <TooltipProvider v-if="!can.create && can.createReason">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <span tabindex="0">
                            <Button disabled>
                                <Lock class="size-4" /> New project
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent class="max-w-xs">
                        {{ can.createReason }}
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <Button
                v-else-if="can.create"
                data-test="new-project"
                @click="openCreate"
            >
                <Plus class="size-4" /> New project
            </Button>
        </div>

        <PageGuide
            id="projects"
            summary="Projects are the example of data an organization owns. You can search, filter, create, edit and delete them. In a real product you'd rename or copy this resource for your own domain, such as invoices, documents or tickets."
            :details="[
                'The `BelongsToOrganization` trait scopes every query to the current organization, so another organization\'s project is a 404.',
                '`ProjectPolicy` checks your role\'s permissions and the plan\'s project limit (3 on Free) before allowing a new project.',
                'A model observer writes each change to the activity log.',
            ]"
        />

        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="relative flex-1 sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search projects…"
                    class="pl-9"
                    aria-label="Search projects"
                />
            </div>
            <div
                class="inline-flex rounded-md border p-0.5 text-sm"
                role="group"
                aria-label="Filter by status"
            >
                <button
                    v-for="option in [{ value: '', label: 'All' }, ...statuses]"
                    :key="option.value"
                    type="button"
                    class="rounded px-3 py-1.5 transition-colors"
                    :class="
                        status === option.value
                            ? 'bg-muted font-medium'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="status === option.value"
                    @click="status = option.value"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div
            v-if="projects.data.length"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="project in projects.data"
                :key="project.id"
                class="group flex flex-col rounded-xl border bg-card p-5 shadow-xs transition-shadow hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 font-semibold text-primary"
                        >
                            {{ project.name.charAt(0) }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="truncate font-medium">
                                {{ project.name }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                Created {{ timeAgo(project.createdAt) }}
                            </p>
                        </div>
                    </div>

                    <DropdownMenu v-if="can.update || can.delete">
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                :aria-label="`Actions for ${project.name}`"
                            >
                                <MoreHorizontal class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem
                                v-if="can.update"
                                @select="openEdit(project)"
                            >
                                <Pencil class="size-4" /> Edit
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="can.delete"
                                variant="destructive"
                                @select="confirmDelete(project)"
                            >
                                <Trash2 class="size-4" /> Delete
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <p
                    class="mt-4 line-clamp-2 flex-1 text-sm text-muted-foreground"
                >
                    {{ project.description || 'No description.' }}
                </p>

                <div
                    class="mt-4 flex items-center justify-between border-t pt-4 text-xs text-muted-foreground"
                >
                    <span>{{ project.creator ?? 'Unknown' }}</span>
                    <Badge variant="outline" class="gap-1.5 capitalize">
                        <span
                            class="size-1.5 rounded-full"
                            :class="statusDot[project.status]"
                        />
                        {{ project.status }}
                    </Badge>
                </div>
            </article>
        </div>

        <EmptyState
            v-else
            :icon="FolderKanban"
            :title="
                filters.search || filters.status
                    ? 'No matching projects'
                    : 'No projects yet'
            "
            :description="
                filters.search || filters.status
                    ? 'Try a different search or filter.'
                    : 'Projects are scoped to this organization — only its members can see them.'
            "
        >
            <Button
                v-if="can.create && !filters.search && !filters.status"
                @click="openCreate"
            >
                <Plus class="size-4" /> Create your first project
            </Button>
        </EmptyState>

        <PaginationLinks :paginator="projects" />
    </div>

    <ProjectFormDialog
        v-model:open="formOpen"
        :project="editing"
        :statuses="statuses"
    />

    <ConfirmDialog
        v-model:open="deleteOpen"
        title="Delete project?"
        :description="`“${deleting?.name}” will be permanently deleted. This cannot be undone.`"
        confirm-label="Delete project"
        :processing="deleteProcessing"
        @confirm="performDelete"
    />
</template>
