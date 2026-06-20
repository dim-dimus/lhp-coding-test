<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import EventFilters from '@/components/events/EventFilters.vue';
import { useEventCalendar } from '@/composables/useEventCalendar';
import { useEventDateTime } from '@/composables/useEventDateTime';
import type { City, EventFilters as EventFiltersType } from '@/types/events';

defineProps<{
    statuses: string[];
    cities: City[];
}>();

const filters = ref<EventFiltersType>({ status: null, from: null, to: null, city: null });

const {
    monthLabel,
    cells,
    maxCount,
    loadingCounts,
    selectedKey,
    dayEvents,
    dayTotal,
    loadingDay,
    loadCounts,
    selectDay,
    prevMonth,
    nextMonth,
    applyFilters,
} = useEventCalendar();

const { formatTime } = useEventDateTime();

const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const selectedLabel = computed(() => {
    if (!selectedKey.value) {
        return '';
    }

    return new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'long', day: 'numeric' }).format(new Date(`${selectedKey.value}T00:00:00`));
});

function onFilterChange() {
    applyFilters({ status: filters.value.status, city: filters.value.city });
}

onMounted(loadCounts);
</script>

<template>
    <Head title="Event Visuals — Calendar" />

    <div class="flex flex-col gap-6 p-4 sm:p-6">
        <div class="flex flex-col gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">Events calendar</h1>
            <p class="text-sm text-muted-foreground">Browse what's on, day by day.</p>
        </div>

        <EventFilters v-model="filters" :statuses="statuses" :cities="cities" :show-dates="false" @change="onFilterChange" />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="rounded-xl border p-3 sm:p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">{{ monthLabel }}</h2>
                    <div class="flex items-center gap-1">
                        <button type="button" class="rounded-md p-2 hover:bg-accent" aria-label="Previous month" @click="prevMonth">
                            <ChevronLeft class="size-4" />
                        </button>
                        <button type="button" class="rounded-md p-2 hover:bg-accent" aria-label="Next month" @click="nextMonth">
                            <ChevronRight class="size-4" />
                        </button>
                    </div>
                </div>

                <div :key="monthLabel" class="grid animate-fade grid-cols-7 gap-1">
                    <div v-for="weekday in weekdays" :key="weekday" class="pb-1 text-center text-xs font-medium text-muted-foreground">
                        {{ weekday }}
                    </div>

                    <button
                        v-for="cell in cells"
                        :key="cell.key"
                        type="button"
                        :disabled="cell.count === 0"
                        class="relative aspect-square overflow-hidden rounded-md border p-1.5 text-left transition"
                        :class="[
                            cell.inMonth ? '' : 'opacity-40',
                            cell.count > 0 ? 'cursor-pointer hover:border-primary' : 'cursor-default',
                            cell.key === selectedKey ? 'ring-2 ring-primary' : '',
                        ]"
                        @click="selectDay(cell)"
                    >
                        <div v-if="cell.count > 0" class="absolute inset-0 bg-[#439cfc]" :style="{ opacity: 0.08 + (cell.count / maxCount) * 0.5 }"></div>
                        <span class="absolute left-1.5 top-1 text-xs" :class="cell.isToday ? 'font-bold text-primary' : 'text-muted-foreground'">{{ cell.day }}</span>
                        <span v-if="cell.count > 0" class="absolute inset-0 flex items-center justify-center text-base font-semibold tabular-nums">{{ cell.count }}</span>
                    </button>
                </div>

                <p class="mt-3 text-xs text-muted-foreground">
                    <span v-if="loadingCounts">Loading…</span>
                    <span v-else>Darker days have more events — click one to see what's on.</span>
                </p>
            </div>

            <div class="rounded-xl border p-4">
                <div v-if="selectedKey" :key="selectedKey" class="animate-fade">
                    <h3 class="font-semibold">{{ selectedLabel }}</h3>
                    <p class="mb-3 text-sm text-muted-foreground">{{ dayTotal }} {{ dayTotal === 1 ? 'event' : 'events' }}</p>

                    <p v-if="loadingDay" class="text-sm text-muted-foreground">Loading…</p>

                    <ul v-else class="flex flex-col gap-2">
                        <li v-for="event in dayEvents" :key="event.id">
                            <Link :href="`/events/${event.id}`" class="flex gap-3 rounded-lg p-2 transition hover:bg-accent">
                                <img :src="event.images[0]" alt="" class="size-14 shrink-0 rounded-md object-cover" />
                                <div class="min-w-0">
                                    <div class="text-xs text-muted-foreground">{{ formatTime(event.starts_at) }}</div>
                                    <div class="truncate font-medium">{{ event.name }}</div>
                                    <div v-if="event.description" class="truncate text-xs text-muted-foreground">{{ event.description }}</div>
                                    <div class="truncate text-xs text-muted-foreground">{{ event.location?.display }}</div>
                                </div>
                            </Link>
                        </li>
                    </ul>

                    <p v-if="!loadingDay && dayTotal > dayEvents.length" class="mt-2 text-xs text-muted-foreground">+ {{ dayTotal - dayEvents.length }} more</p>
                </div>

                <div v-else class="flex h-full min-h-40 items-center justify-center text-center text-sm text-muted-foreground">
                    Select a day to see its events.
                </div>
            </div>
        </div>
    </div>
</template>
