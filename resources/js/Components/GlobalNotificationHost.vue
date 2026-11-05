<script setup>
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import GlobalConfirmDialog from '@/Components/GlobalConfirmDialog.vue';
import GlobalToast from '@/Components/GlobalToast.vue';
import { useNotification } from '@/Composables/useNotification';

const page = usePage();
const { notifyFromFlash } = useNotification();

watch(
    () => page.props?.flash?.notification,
    (flashNotification) => {
        notifyFromFlash(flashNotification);
    },
    { immediate: true },
);
</script>

<template>
    <GlobalToast />
    <GlobalConfirmDialog />
</template>
