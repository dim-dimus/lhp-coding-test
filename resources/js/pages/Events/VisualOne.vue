<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import EventCard from '@/components/events/EventCard.vue';
import EventFilters from '@/components/events/EventFilters.vue';
import RegisterDialog from '@/components/events/RegisterDialog.vue';
import { useEventFeed } from '@/composables/useEventFeed';
import type { City, EventFilters as EventFiltersType, EventRow } from '@/types/events';

const props = defineProps<{
    statuses: string[];
    cities: City[];
    filters: EventFiltersType;
}>();

const filters = ref<EventFiltersType>({ ...props.filters });

const { rows, total, loading, hasLoadedOnce, sentinel, loadedBytes, loadedMs, applyFilters } = useEventFeed();

const registerOpen = ref(false);
const registerEvent = ref<EventRow | null>(null);

function onRegister(event: EventRow) {
    registerEvent.value = event;
    registerOpen.value = true;
}

const loadedSize = computed(() => {
    const kb = loadedBytes.value / 1024;

    return kb < 1024 ? `${kb.toFixed(1)} KB` : `${(kb / 1024).toFixed(2)} MB`;
});

function reload() {
    applyFilters({ ...filters.value });
}

onMounted(reload);
</script>

<template>
    <Head title="Event Visuals — Grid" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Browse events</h1>
            <p class="text-sm text-muted-foreground">
                {{ total !== null ? `${total.toLocaleString()} events` : 'Loading…' }}
            </p>
        </div>

        <EventFilters v-model="filters" :statuses="statuses" :cities="cities" @change="reload" />

        <div v-if="rows.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <EventCard v-for="event in rows" :key="event.id" :event="event" @register="onRegister" />
        </div>

        <div v-else-if="hasLoadedOnce && !loading" class="rounded-xl border border-dashed py-16 text-center text-muted-foreground">
            No events match your filters.
        </div>

        <div ref="sentinel" class="h-px"></div>

        <div class="pb-4 text-center text-sm text-muted-foreground">
            <span v-if="loading">Loading…</span>
            <span v-else-if="hasLoadedOnce">Loaded {{ loadedSize }} · {{ (loadedMs / 1000).toFixed(1) }}s</span>
        </div>

        <RegisterDialog v-model:open="registerOpen" :event="registerEvent" />
    </div>
</template>
