import { useIntersectionObserver } from '@vueuse/core';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import type { EventFeedResponse, EventFilters, EventRow } from '@/types/events';

export interface UseEventFeedReturn {
    rows: Ref<EventRow[]>;
    total: Ref<number | null>;
    loading: Ref<boolean>;
    hasLoadedOnce: Ref<boolean>;
    hasMore: ComputedRef<boolean>;
    loadedBytes: Ref<number>;
    loadedMs: Ref<number>;
    sentinel: Ref<HTMLElement | null>;
    applyFilters: (filters: EventFilters) => Promise<void>;
    loadMore: () => Promise<void>;
}

/**
 * Drives the `/events/data` JSON endpoint: pagination, infinite scroll and the
 * load stats. Filtering is applied via applyFilters(), which resets and reloads.
 */
export function useEventFeed(): UseEventFeedReturn {
    const rows = ref<EventRow[]>([]);
    const page = ref(0);
    const lastPage = ref<number | null>(null);
    const total = ref<number | null>(null);
    const loadedBytes = ref(0);
    const loadedMs = ref(0);
    const loading = ref(false);
    const hasLoadedOnce = ref(false);
    const sentinel = ref<HTMLElement | null>(null);

    let filters: EventFilters = { status: null, from: null, to: null, city: null };

    const hasMore = computed(() => lastPage.value === null || page.value < lastPage.value);

    async function loadMore(): Promise<void> {
        if (loading.value || !hasMore.value) {
            return;
        }

        loading.value = true;

        const params = new URLSearchParams({ page: String(page.value + 1) });

        if (filters.status) {
            params.set('status', filters.status);
        }

        if (filters.from) {
            params.set('from', filters.from);
        }

        if (filters.to) {
            params.set('to', filters.to);
        }

        if (filters.city) {
            params.set('city', filters.city);
        }

        try {
            const response = await fetch(`/events/data?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const payload: EventFeedResponse = await response.json();

            rows.value.push(...payload.data);
            page.value = payload.current_page;
            lastPage.value = payload.last_page;
            total.value = payload.total;
            loadedBytes.value += payload.stats.bytes;
            loadedMs.value += payload.stats.ms;
            hasLoadedOnce.value = true;
        } finally {
            loading.value = false;
        }
    }

    async function applyFilters(next: EventFilters): Promise<void> {
        filters = { ...next };
        rows.value = [];
        page.value = 0;
        lastPage.value = null;
        total.value = null;
        loadedBytes.value = 0;
        loadedMs.value = 0;
        hasLoadedOnce.value = false;
        await loadMore();
    }

    useIntersectionObserver(
        sentinel,
        ([entry]) => {
            if (entry?.isIntersecting) {
                loadMore();
            }
        },
        { rootMargin: '400px' },
    );

    return {
        rows,
        total,
        loading,
        hasLoadedOnce,
        hasMore,
        loadedBytes,
        loadedMs,
        sentinel,
        applyFilters,
        loadMore,
    };
}
