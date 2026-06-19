<script setup lang="ts">
import type { City, EventFilters } from '@/types/events';

const filters = defineModel<EventFilters>({ required: true });

withDefaults(
    defineProps<{
        statuses: string[];
        cities: City[];
        showDates?: boolean;
    }>(),
    { showDates: true },
);

const emit = defineEmits<{ change: [] }>();

function reset() {
    filters.value = { status: null, from: null, to: null, city: null };
    emit('change');
}
</script>

<template>
    <form class="flex flex-wrap items-end gap-3" @submit.prevent>
        <div class="flex flex-col gap-1">
            <label class="text-xs text-muted-foreground" for="filter-status">Status</label>
            <select
                id="filter-status"
                v-model="filters.status"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                @change="emit('change')"
            >
                <option :value="null">All statuses</option>
                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-xs text-muted-foreground" for="filter-city">Location</label>
            <select
                id="filter-city"
                v-model="filters.city"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                @change="emit('change')"
            >
                <option :value="null">All locations</option>
                <option v-for="city in cities" :key="city.value" :value="city.value">{{ city.label }}</option>
            </select>
        </div>

        <template v-if="showDates">
            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="filter-from">From</label>
                <input
                    id="filter-from"
                    v-model="filters.from"
                    type="date"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    @change="emit('change')"
                />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs text-muted-foreground" for="filter-to">To</label>
                <input
                    id="filter-to"
                    v-model="filters.to"
                    type="date"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    @change="emit('change')"
                />
            </div>
        </template>

        <button type="button" class="h-9 px-3 text-sm text-muted-foreground hover:text-foreground" @click="reset">
            Reset
        </button>
    </form>
</template>
