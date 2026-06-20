<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { EventRow } from '@/types/events';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{ event: EventRow | null }>();

const form = useForm({ name: '', email: '' });
const done = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
        done.value = false;
    }
});

function submit() {
    if (!props.event) {
        return;
    }

    form.post(`/events/${props.event.id}/attendees`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            done.value = true;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Register your interest</DialogTitle>
                <DialogDescription>{{ event?.name }}</DialogDescription>
            </DialogHeader>

            <div v-if="done" class="flex flex-col gap-3">
                <p class="animate-fade rounded-md bg-primary/10 px-3 py-2 text-sm text-primary">You're on the list — see you there!</p>
                <button type="button" class="h-9 rounded-md border text-sm font-medium transition hover:bg-accent" @click="open = false">Done</button>
            </div>

            <form v-else class="flex flex-col gap-3" @submit.prevent="submit">
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-muted-foreground" for="register-name">Name</label>
                    <input id="register-name" v-model="form.name" type="text" class="h-9 rounded-md border border-input bg-background px-3 text-sm" />
                    <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-muted-foreground" for="register-email">Email</label>
                    <input id="register-email" v-model="form.email" type="email" class="h-9 rounded-md border border-input bg-background px-3 text-sm" />
                    <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="h-9 rounded-md bg-primary text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                >
                    Register
                </button>
            </form>
        </DialogContent>
    </Dialog>
</template>
