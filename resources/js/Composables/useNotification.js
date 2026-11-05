import { readonly, ref, toRaw } from 'vue';

export const MAX_TOASTS = 3;
export const DEFAULT_TOAST_DURATION = 4000;

const VALID_TYPES = ['success', 'error', 'warning', 'info'];

const toasts = ref([]);
const timers = new Map();
const handledFlashObjects = new WeakSet();
let nextToastId = 1;

function normalizeOptions(options) {
    if (typeof options === 'string') {
        return { title: options };
    }

    return options && typeof options === 'object' ? options : {};
}

function dismiss(id) {
    if (timers.has(id)) {
        clearTimeout(timers.get(id));
        timers.delete(id);
    }

    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function clear() {
    timers.forEach((timerId) => clearTimeout(timerId));
    timers.clear();
    toasts.value = [];
}

function notify(payload) {
    const raw =
        typeof payload === 'string'
            ? { type: 'info', message: payload }
            : payload || {};

    const message = String(raw.message ?? '').trim();
    if (!message) {
        return null;
    }

    const type = VALID_TYPES.includes(raw.type) ? raw.type : 'info';
    const title = String(raw.title ?? '').trim();
    const duration =
        typeof raw.duration === 'number' ? raw.duration : DEFAULT_TOAST_DURATION;

    while (toasts.value.length >= MAX_TOASTS) {
        const oldest = toasts.value[0];
        if (!oldest) {
            break;
        }
        dismiss(oldest.id);
    }

    const id = nextToastId++;
    const item = {
        id,
        type,
        title,
        message,
        duration,
    };

    toasts.value = [...toasts.value, item];

    if (duration > 0) {
        const timerId = setTimeout(() => {
            dismiss(id);
        }, duration);
        timers.set(id, timerId);
    }

    return id;
}

function success(message, options = {}) {
    return notify({ ...normalizeOptions(options), type: 'success', message });
}

function error(message, options = {}) {
    return notify({ ...normalizeOptions(options), type: 'error', message });
}

function warning(message, options = {}) {
    return notify({ ...normalizeOptions(options), type: 'warning', message });
}

function info(message, options = {}) {
    return notify({ ...normalizeOptions(options), type: 'info', message });
}

function notifyFromFlash(flashNotification) {
    if (!flashNotification) {
        return null;
    }

    if (typeof flashNotification === 'string') {
        return notify({ type: 'info', message: flashNotification });
    }

    if (typeof flashNotification === 'object' && flashNotification.message) {
        const rawTarget = toRaw(flashNotification);
        if (handledFlashObjects.has(rawTarget)) {
            return null;
        }

        handledFlashObjects.add(rawTarget);
        return notify({
            type: flashNotification.type || 'info',
            title: flashNotification.title || '',
            message: flashNotification.message,
        });
    }

    return null;
}

export function useNotification() {
    return {
        toasts: readonly(toasts),
        notify,
        notifyFromFlash,
        success,
        error,
        warning,
        info,
        dismiss,
        clear,
    };
}
