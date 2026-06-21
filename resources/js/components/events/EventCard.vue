<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, Images, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useEventDateTime } from '@/composables/useEventDateTime';
import { formatPrice } from '@/composables/useEventPrice';
import type { EventRow } from '@/types/events';

const props = defineProps<{ event: EventRow }>();

const emit = defineEmits<{ register: [EventRow] }>();

const { formatDateTime, relative } = useEventDateTime();

const statusVariant = computed(() => {
    const variants = { published: 'default', cancelled: 'destructive', sold_out: 'secondary' } as const;

    return variants[props.event.status as keyof typeof variants] ?? 'outline';
});

const price = computed(() => formatPrice(props.event.price));
</script>

<template>
    <div
        class="group flex animate-rise flex-col overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-lg"
    >
        <Link :href="`/events/${event.id}`" class="flex flex-1 flex-col">
            <div class="relative aspect-video overflow-hidden bg-muted">
                <!-- Cover image cross-fades to the second image on hover, surfacing the 2+ images per event. -->
                <img
                    :src="event.images[0]"
                    alt=""
                    loading="lazy"
                    class="absolute inset-0 size-full object-cover transition-opacity duration-500 group-hover:opacity-0"
                />
                <img
                    v-if="event.images[1]"
                    :src="event.images[1]"
                    alt=""
                    loading="lazy"
                    class="absolute inset-0 size-full object-cover opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                />
                <span class="absolute left-3 top-3 rounded-full bg-black/55 px-2.5 py-0.5 text-xs font-medium capitalize text-white backdrop-blur">
                    {{ event.type }}
                </span>
                <span
                    v-if="event.images.length > 1"
                    class="absolute bottom-3 right-3 flex items-center gap-1 rounded-full bg-black/55 px-2 py-0.5 text-xs text-white backdrop-blur"
                >
                    <Images class="size-3" />
                    {{ event.images.length }}
                </span>
            </div>

            <div class="flex flex-1 flex-col gap-2 p-4">
                <div class="flex items-center justify-between gap-2">
                    <Badge :variant="statusVariant" class="capitalize">{{ event.status.replace('_', ' ') }}</Badge>
                    <span v-if="price" class="text-sm font-semibold">{{ price }}</span>
                </div>

                <h3 class="line-clamp-1 font-semibold leading-tight">{{ event.name }}</h3>
                <p v-if="event.description" class="line-clamp-2 text-sm text-muted-foreground">{{ event.description }}</p>

                <div class="mt-auto space-y-1 pt-2 text-sm">
                    <div class="flex items-center gap-1.5 text-muted-foreground">
                        <MapPin class="size-3.5 shrink-0" />
                        <span class="truncate">{{ event.location?.display ?? 'Location TBA' }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <CalendarDays class="size-3.5 shrink-0 text-muted-foreground" />
                        <span>{{ formatDateTime(event.starts_at) }}</span>
                    </div>
                    <div v-if="event.starts_at" class="pl-5 text-xs text-muted-foreground">{{ relative(event.starts_at) }}</div>
                </div>
            </div>
        </Link>

        <div class="px-4 pb-4">
            <button
                type="button"
                class="h-9 w-full rounded-md bg-primary text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                @click="emit('register', event)"
            >
                Register
            </button>
        </div>
    </div>
</template>
