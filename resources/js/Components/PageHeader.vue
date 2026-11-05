<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div data-testid="page-header" class="space-y-2">
        <slot name="breadcrumb" />

        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="min-w-0">
                <h1
                    data-testid="page-header-title"
                    class="text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl"
                >
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    data-testid="page-header-description"
                    class="mt-1 text-sm text-slate-600"
                >
                    {{ description }}
                </p>
            </div>

            <div
                v-if="$slots.actions || $slots.default"
                data-testid="page-header-actions"
                class="flex shrink-0 flex-wrap items-center gap-2.5"
            >
                <slot name="actions">
                    <slot />
                </slot>
            </div>
        </div>
    </div>
</template>
