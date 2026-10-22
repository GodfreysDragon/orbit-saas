<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown, Info } from '@lucide/vue';
import { ref, useId, watch } from 'vue';

const props = defineProps<{
    // Remembers whether this page's guide was collapsed.
    id: string;
    // What the page does, for someone exploring the demo.
    summary: string;
    // How it's built. `backticks` render as code.
    details?: string[];
}>();

const enabled = usePage().props.pageGuides;
const storageKey = `page-guide:${props.id}`;
const contentId = useId();

function readOpen(): boolean {
    try {
        return localStorage.getItem(storageKey) !== 'closed';
    } catch {
        return true;
    }
}

const open = ref(readOpen());

watch(open, (value) => {
    try {
        localStorage.setItem(storageKey, value ? 'open' : 'closed');
    } catch {
        // Storage can be unavailable (private mode); the guide still works.
    }
});

const codeParts = (text: string) =>
    text.split('`').map((part, i) => ({ text: part, code: i % 2 === 1 }));
</script>

<template>
    <section
        v-if="enabled"
        class="rounded-xl border border-primary/20 bg-primary/5 text-sm"
        data-test="page-guide"
    >
        <button
            type="button"
            :aria-expanded="open"
            :aria-controls="contentId"
            @click="open = !open"
            class="flex w-full items-center gap-2 rounded-xl px-4 py-3 text-left font-medium outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
        >
            <Info class="size-4 shrink-0 text-primary" aria-hidden="true" />
            About this page
            <ChevronDown
                class="ml-auto size-4 text-muted-foreground transition-transform"
                :class="open && 'rotate-180'"
                aria-hidden="true"
            />
        </button>
        <div v-show="open" :id="contentId" class="space-y-3 px-4 pb-4 pl-10">
            <p>{{ summary }}</p>
            <div v-if="details?.length" class="space-y-1.5">
                <p
                    class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    How it's built
                </p>
                <ul
                    class="list-disc space-y-1 pl-4 text-muted-foreground marker:text-primary/50"
                >
                    <li v-for="detail in details" :key="detail">
                        <template
                            v-for="(part, i) in codeParts(detail)"
                            :key="i"
                        >
                            <code
                                v-if="part.code"
                                class="rounded bg-muted px-1 py-0.5 font-mono text-[0.8em] text-foreground"
                                >{{ part.text }}</code
                            >
                            <template v-else>{{ part.text }}</template>
                        </template>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
