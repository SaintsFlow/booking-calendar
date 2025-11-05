import { ref } from "vue";

const toasts = ref([]);
let nextId = 0;

export function useToast() {
    const addToast = (options) => {
        const id = nextId++;
        const toast = {
            id,
            show: true,
            type: options.type || "info",
            title: options.title,
            message: options.message || "",
            duration: options.duration !== undefined ? options.duration : 5000,
        };

        toasts.value.push(toast);

        return id;
    };

    const removeToast = (id) => {
        const index = toasts.value.findIndex((t) => t.id === id);
        if (index > -1) {
            toasts.value[index].show = false;
            setTimeout(() => {
                toasts.value.splice(index, 1);
            }, 300);
        }
    };

    const success = (title, message = "", duration) => {
        return addToast({ type: "success", title, message, duration });
    };

    const error = (title, message = "", duration) => {
        return addToast({ type: "error", title, message, duration });
    };

    const warning = (title, message = "", duration) => {
        return addToast({ type: "warning", title, message, duration });
    };

    const info = (title, message = "", duration) => {
        return addToast({ type: "info", title, message, duration });
    };

    return {
        toasts,
        addToast,
        removeToast,
        success,
        error,
        warning,
        info,
    };
}
