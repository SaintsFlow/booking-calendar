import axios from "axios";
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;

// CSRF Token
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common["X-CSRF-TOKEN"] = token.content;
} else {
    console.error("CSRF token not found in page header");
}

// Автоматическая перезагрузка при CSRF ошибке
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 419) {
            console.error("CSRF token mismatch - session may have expired");
            // Можно попробовать получить новый токен или перезагрузить
            if (confirm("Сессия истекла. Перезагрузить страницу?")) {
                window.location.reload();
            }
        }
        return Promise.reject(error);
    }
);

// Laravel Echo setup для Reverb
window.Pusher = Pusher;

if (import.meta.env.VITE_BROADCAST_DRIVER === "reverb") {
    window.Echo = new Echo({
        broadcaster: "reverb",
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
        enabledTransports: ["ws", "wss"],
        authorizer: (channel, options) => {
            return {
                authorize: (socketId, callback) => {
                    const token = document.head.querySelector(
                        'meta[name="csrf-token"]'
                    );

                    window.axios
                        .post(
                            "/broadcasting/auth",
                            {
                                socket_id: socketId,
                                channel_name: channel.name,
                            },
                            {
                                headers: {
                                    "X-CSRF-TOKEN": token ? token.content : "",
                                    Accept: "application/json",
                                    "Content-Type": "application/json",
                                },
                                withCredentials: true,
                            }
                        )
                        .then((response) => {
                            callback(null, response.data);
                        })
                        .catch((error) => {
                            console.error(
                                "Broadcasting authorization error:",
                                error
                            );
                            callback(error);
                        });
                },
            };
        },
    });

    console.log("Laravel Echo initialized with Reverb");
}
