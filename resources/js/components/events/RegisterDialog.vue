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
                <p class="animate-fade bg-primary/10 text-primary rounded-md px-3 py-2 text-sm">You're on the list — see you there!</p>
                <button type="button" class="hover:bg-accent h-9 rounded-md border text-sm font-medium transition" @click="open = false">Done</button>
            </div>

            <form v-else class="flex flex-col gap-3" @submit.prevent="submit">
                <div class="flex flex-col gap-1">
                    <label class="text-muted-foreground text-xs" for="register-name">Name</label>
                    <input id="register-name" v-model="form.name" type="text" class="border-input bg-background h-9 rounded-md border px-3 text-sm" />
                    <p v-if="form.errors.name" class="text-destructive text-xs">{{ form.errors.name }}</p>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-muted-foreground text-xs" for="register-email">Email</label>
                    <input
                        id="register-email"
                        v-model="form.email"
                        type="email"
                        class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    />
                    <p v-if="form.errors.email" class="text-destructive text-xs">{{ form.errors.email }}</p>
                </div>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="bg-primary text-primary-foreground hover:bg-primary/90 h-9 rounded-md text-sm font-medium transition disabled:opacity-50"
                >
                    Register
                </button>
            </form>
        </DialogContent>
    </Dialog>
</template>
