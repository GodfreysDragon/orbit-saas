<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/projects';
import type { Project } from '@/types';

const props = defineProps<{
    project: Project | null;
    statuses: { value: string; label: string }[];
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
    name: '',
    description: '',
    status: 'active',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.defaults({
        name: props.project?.name ?? '',
        description: props.project?.description ?? '',
        status: props.project?.status ?? 'active',
    });
    form.reset();
});

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.project) {
        form.put(update(props.project.id).url, options);
    } else {
        form.post(store().url, options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>
                        {{ project ? 'Edit project' : 'New project' }}
                    </DialogTitle>
                    <DialogDescription>
                        Projects are visible to everyone in this organization.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="project-name">Name</Label>
                    <Input
                        id="project-name"
                        v-model="form.name"
                        placeholder="Website redesign"
                        required
                        autofocus
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="project-description">Description</Label>
                    <Textarea
                        id="project-description"
                        v-model="form.description"
                        rows="3"
                        placeholder="What is this project about?"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div v-if="project" class="grid gap-2">
                    <Label for="project-status">Status</Label>
                    <Select v-model="form.status">
                        <SelectTrigger id="project-status" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="status in statuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="open = false"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ project ? 'Save changes' : 'Create project' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
