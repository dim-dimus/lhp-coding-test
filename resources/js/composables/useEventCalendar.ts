import { computed, ref } from 'vue';
import type { EventFeedResponse, EventRow } from '@/types/events';

export interface CalendarCell {
    date: Date;
    key: string;
    day: number;
    inMonth: boolean;
    isToday: boolean;
    count: number;
}

interface CalendarFilters {
    status: string | null;
    city: string | null;
}

function pad(value: number): string {
    return value < 10 ? `0${value}` : `${value}`;
}

/** Local YYYY-MM-DD for a Date (matches the server's local-day bucketing). */
function dayKey(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function addDays(date: Date, days: number): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate() + days);
}

function toTimestamp(date: Date): number {
    return Math.floor(date.getTime() / 1000);
}

/**
 * Drives the calendar (Visual Two): per-day counts for the visible month and
 * the events for a selected day. All time bounds are computed from local Date
 * objects so the cells line up with the viewer's timezone.
 */
export function useEventCalendar() {
    const today = new Date();
    const monthAnchor = ref(new Date(today.getFullYear(), today.getMonth(), 1));
    const counts = ref<Record<string, number>>({});
    const loadingCounts = ref(false);

    const selectedKey = ref<string | null>(null);
    const dayEvents = ref<EventRow[]>([]);
    const dayTotal = ref(0);
    const loadingDay = ref(false);

    // Seconds to add to a UTC timestamp to reach the viewer's local wall-clock.
    const offsetSeconds = -new Date().getTimezoneOffset() * 60;

    let filters: CalendarFilters = { status: null, city: null };

    const gridStart = computed(() => {
        const first = monthAnchor.value;
        const mondayOffset = (first.getDay() + 6) % 7;

        return addDays(first, -mondayOffset);
    });

    const monthLabel = computed(() =>
        new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(monthAnchor.value),
    );

    const maxCount = computed(() => Math.max(1, ...Object.values(counts.value)));

    const cells = computed<CalendarCell[]>(() => {
        const todayKey = dayKey(new Date());
        const month = monthAnchor.value.getMonth();

        return Array.from({ length: 42 }, (_, index) => {
            const date = addDays(gridStart.value, index);
            const key = dayKey(date);

            return {
                date,
                key,
                day: date.getDate(),
                inMonth: date.getMonth() === month,
                isToday: key === todayKey,
                count: counts.value[key] ?? 0,
            };
        });
    });

    function withFilters(params: URLSearchParams): URLSearchParams {
        if (filters.status) {
            params.set('status', filters.status);
        }

        if (filters.city) {
            params.set('city', filters.city);
        }

        return params;
    }

    async function loadCounts(): Promise<void> {
        loadingCounts.value = true;

        const params = withFilters(
            new URLSearchParams({
                start: String(toTimestamp(gridStart.value)),
                end: String(toTimestamp(addDays(gridStart.value, 42))),
                offset: String(offsetSeconds),
            }),
        );

        try {
            const response = await fetch(`/events/calendar?${params.toString()}`, { headers: { Accept: 'application/json' } });
            const payload: { counts?: Record<string, number> } = await response.json();

            counts.value = payload.counts ?? {};
        } finally {
            loadingCounts.value = false;
        }
    }

    async function fetchDay(cell: CalendarCell): Promise<void> {
        loadingDay.value = true;

        const params = withFilters(
            new URLSearchParams({
                start: String(toTimestamp(cell.date)),
                end: String(toTimestamp(addDays(cell.date, 1))),
            }),
        );

        try {
            const response = await fetch(`/events/data?${params.toString()}`, { headers: { Accept: 'application/json' } });
            const payload: EventFeedResponse = await response.json();

            dayEvents.value = payload.data;
            dayTotal.value = payload.total;
        } finally {
            loadingDay.value = false;
        }
    }

    function selectDay(cell: CalendarCell): void {
        selectedKey.value = cell.key;
        fetchDay(cell);
    }

    function changeMonth(delta: number): void {
        monthAnchor.value = new Date(monthAnchor.value.getFullYear(), monthAnchor.value.getMonth() + delta, 1);
        selectedKey.value = null;
        dayEvents.value = [];
        loadCounts();
    }

    function applyFilters(next: CalendarFilters): void {
        filters = { ...next };
        loadCounts();

        const selected = cells.value.find((cell) => cell.key === selectedKey.value);

        if (selected) {
            fetchDay(selected);
        }
    }

    return {
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
        prevMonth: () => changeMonth(-1),
        nextMonth: () => changeMonth(1),
        applyFilters,
    };
}
