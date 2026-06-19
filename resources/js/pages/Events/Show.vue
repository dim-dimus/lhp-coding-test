<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, CalendarDays, MapPin, Ticket, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useEventDateTime } from '@/composables/useEventDateTime';
import type { EventRow } from '@/types/events';

const props = defineProps<{
    event: EventRow;
    attendeesCount: number;
}>();

const { formatDateTime, relative } = useEventDateTime();

const activeImage = ref<string>(props.event.images[0] ?? '');

const statusVariant = computed(() => {
    const variants = { published: 'default', cancelled: 'destructive', sold_out: 'secondary' } as const;

    return variants[props.event.status as keyof typeof variants] ?? 'outline';
});

const price = computed(() => {
    const value = props.event.price;

    if (!value) {
        return null;
    }

    if (value.amount === 0) {
        return 'Free';
    }

    return new Intl.NumberFormat(undefined, { style: 'currency', currency: value.currency }).format(value.amount);
});

const form = useForm({ name: '', email: '' });

function register() {
    form.post(`/events/${props.event.id}/attendees`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="event.name" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6">
        <Link href="/events-visual-1" class="text-sm text-muted-foreground hover:text-foreground">← Back to events</Link>

        <div class="flex flex-col gap-3">
            <div class="relative aspect-video overflow-hidden rounded-xl border bg-muted">
                <img :src="activeImage" alt="" class="size-full object-cover" />
                <Badge :variant="statusVariant" class="absolute left-3 top-3 capitalize">{{ event.status.replace('_', ' ') }}</Badge>
            </div>
            <div v-if="event.images.length > 1" class="flex gap-2">
                <button
                    v-for="(image, index) in event.images"
                    :key="index"
                    type="button"
                    class="aspect-video w-24 overflow-hidden rounded-md border transition"
                    :class="image === activeImage ? 'ring-2 ring-primary' : 'opacity-70 hover:opacity-100'"
                    @click="activeImage = image"
                >
                    <img :src="image" alt="" class="size-full object-cover" />
                </button>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <span class="text-sm font-medium capitalize text-muted-foreground">{{ event.type }}</span>
                    <h1 class="text-2xl font-semibold tracking-tight">{{ event.name }}</h1>
                </div>
                <p v-if="event.description" class="leading-relaxed text-muted-foreground">{{ event.description }}</p>
            </div>

            <aside class="flex flex-col gap-4">
                <div class="flex flex-col gap-3 rounded-xl border p-4 text-sm">
                    <div class="flex items-start gap-2">
                        <CalendarDays class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <div>
                            <div>{{ formatDateTime(event.starts_at) }}</div>
                            <div v-if="event.starts_at" class="text-xs text-muted-foreground">{{ relative(event.starts_at) }}</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span>{{ event.location?.display ?? 'Location TBA' }}</span>
                    </div>
                    <div v-if="event.venue" class="flex items-start gap-2">
                        <Building2 class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span>{{ event.venue }}</span>
                    </div>
                    <div v-if="price" class="flex items-start gap-2">
                        <Ticket class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span>{{ price }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <Users class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                        <span>{{ attendeesCount.toLocaleString() }} {{ attendeesCount === 1 ? 'person' : 'people' }} going</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 rounded-xl border p-4">
                    <h2 class="font-semibold">Register your interest</h2>

                    <div v-if="form.recentlySuccessful" class="rounded-md bg-primary/10 px-3 py-2 text-sm text-primary">
                        You're on the list — see you there!
                    </div>

                    <form class="flex flex-col gap-3" @submit.prevent="register">
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-muted-foreground" for="attendee-name">Name</label>
                            <input id="attendee-name" v-model="form.name" type="text" class="h-9 rounded-md border border-input bg-background px-3 text-sm" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="flex flex-col gap-1">
                            <label class="text-xs text-muted-foreground" for="attendee-email">Email</label>
                            <input id="attendee-email" v-model="form.email" type="email" class="h-9 rounded-md border border-input bg-background px-3 text-sm" />
                            <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                        </div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-9 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                        >
                            Register
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    </div>
</template>
