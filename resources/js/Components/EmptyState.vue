<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Inbox } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    icon: {
        type: [Object, Function],
        default: () => Inbox,
    },
    actionLabel: {
        type: String,
        default: '',
    },
    actionHref: {
        type: String,
        default: '',
    },
    operatorOnly: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['action']);

const page = usePage();

const canShowAction = computed(() => {
    if (!props.actionLabel) {
        return false;
    }

    if (!props.operatorOnly) {
        return true;
    }

    const role = page.props?.auth?.user?.role;
    return role === 'operator';
});
</script>

<template>
    <div
        data-testid="empty-state"
        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-white px-6 py-12 text-center"
    >
        <div
            class="flex h-11 w-11 items-center justify-center rounded-lg bg-slate-100 text-slate-400"
        >
            <component :is="icon" class="h-5 w-5" :stroke-width="1.75" />
        </div>

        <p
            data-testid="empty-state-message"
            class="mt-3 max-w-md text-sm text-slate-600"
        >
            {{ title }}
        </p>

        <div v-if="canShowAction" class="mt-4">
            <BaseButton
                data-testid="empty-state-action"
                variant="primary"
                size="md"
                :href="actionHref"
                @click="!actionHref && emit('action')"
            >
                {{ actionLabel }}
            </BaseButton>
        </div>
    </div>
</template>
