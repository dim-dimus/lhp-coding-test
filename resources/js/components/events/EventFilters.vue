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
            <label class="text-muted-foreground text-xs" for="filter-status">Status</label>
            <select
                id="filter-status"
                v-model="filters.status"
                class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                @change="emit('change')"
            >
                <option :value="null">All statuses</option>
                <option v-for="status in statuses" :key="status" :value="status">{{ status }}</option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="text-muted-foreground text-xs" for="filter-city">Location</label>
            <select
                id="filter-city"
                v-model="filters.city"
                class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                @change="emit('change')"
            >
                <option :value="null">All locations</option>
                <option v-for="city in cities" :key="city.value" :value="city.value">{{ city.label }}</option>
            </select>
        </div>

        <template v-if="showDates">
            <div class="flex flex-col gap-1">
                <label class="text-muted-foreground text-xs" for="filter-from">From</label>
                <input
                    id="filter-from"
                    v-model="filters.from"
                    type="date"
                    class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    @change="emit('change')"
                />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-muted-foreground text-xs" for="filter-to">To</label>
                <input
                    id="filter-to"
                    v-model="filters.to"
                    type="date"
                    class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    @change="emit('change')"
                />
            </div>
        </template>

        <button type="button" class="text-muted-foreground hover:text-foreground h-9 px-3 text-sm" @click="reset">Reset</button>
    </form>
</template>
