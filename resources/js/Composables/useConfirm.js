import { reactive, readonly } from 'vue';

const DEFAULT_STATE = {
    isOpen: false,
    title: 'Konfirmasi Tindakan',
    message: '',
    variant: 'danger',
    confirmText: 'Ya, Lanjutkan',
    cancelText: 'Batal',
};

const dialogState = reactive({ ...DEFAULT_STATE });
let activeResolver = null;

function resolvePending(result) {
    if (activeResolver) {
        const resolver = activeResolver;
        activeResolver = null;
        resolver(result);
    }
}

function ask(options = {}) {
    // Jika masih ada dialog sebelumnya yang menggantung, selesaikan sebagai batal (false)
    resolvePending(false);

    const normalized =
        typeof options === 'string'
            ? { message: options }
            : options || {};

    dialogState.title = normalized.title || DEFAULT_STATE.title;
    dialogState.message = normalized.message || '';
    dialogState.variant = normalized.variant || DEFAULT_STATE.variant;
    dialogState.confirmText =
        normalized.confirmText ||
        normalized.confirmLabel ||
        DEFAULT_STATE.confirmText;
    dialogState.cancelText =
        normalized.cancelText ||
        normalized.cancelLabel ||
        DEFAULT_STATE.cancelText;
    dialogState.isOpen = true;

    return new Promise((resolve) => {
        activeResolver = resolve;
    });
}

function accept() {
    dialogState.isOpen = false;
    resolvePending(true);
}

function cancel() {
    dialogState.isOpen = false;
    resolvePending(false);
}

function reset() {
    resolvePending(false);
    Object.assign(dialogState, DEFAULT_STATE);
}

export function useConfirm() {
    return {
        state: readonly(dialogState),
        ask,
        confirm: ask,
        accept,
        cancel,
        reset,
    };
}
