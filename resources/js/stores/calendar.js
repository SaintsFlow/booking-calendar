import { defineStore } from "pinia";
import { ref, computed } from "vue";
import { useAuthStore } from "./auth";

export const useCalendarStore = defineStore("calendar", () => {
    const authStore = useAuthStore();

    // State
    const bookings = ref([]);
    const workplaces = ref([]);
    const employees = ref([]);
    const services = ref([]);
    const statuses = ref([]);
    const clients = ref([]);
    const loading = ref(false);

    // Filter dictionary - доступные значения для фильтров
    const filterDictionary = ref({
        workplaces: [],
        employees: [],
        statuses: [],
    });

    // Filters
    const currentView = ref("week"); // day | week | month
    const currentDate = ref(new Date());
    const selectedWorkplaceId = ref(null);
    const selectedEmployeeId = ref(null);
    const selectedStatusId = ref(null);
    const showCancelled = ref(false); // Показывать ли отменённые бронирования

    // Computed
    const filteredBookings = computed(() => {
        let filtered = bookings.value;

        if (selectedWorkplaceId.value) {
            filtered = filtered.filter(
                (b) => b.workplace_id === selectedWorkplaceId.value
            );
        }

        if (selectedEmployeeId.value) {
            filtered = filtered.filter(
                (b) => b.employee_id === selectedEmployeeId.value
            );
        }

        if (selectedStatusId.value) {
            filtered = filtered.filter(
                (b) => b.status_id === selectedStatusId.value
            );
        }

        return filtered;
    });

    const activeWorkplaces = computed(() =>
        workplaces.value.filter((w) => w.is_active)
    );

    const activeServices = computed(() =>
        services.value.filter((s) => s.is_active)
    );

    // Actions
    async function fetchCalendar() {
        try {
            loading.value = true;
            const params = {
                view: currentView.value,
                date: currentDate.value.toISOString().split("T")[0],
            };

            if (selectedWorkplaceId.value) {
                params.workplace_id = selectedWorkplaceId.value;
            }

            if (selectedEmployeeId.value) {
                params.employee_id = selectedEmployeeId.value;
            }

            if (selectedStatusId.value) {
                params.status_id = selectedStatusId.value;
            }

            // Добавляем параметр для отображения отменённых бронирований
            if (showCancelled.value) {
                params.show_cancelled = true;
            }

            const response = await window.axios.get("/api/calendar", {
                params,
            });

            // Извлекаем бронирования из структуры календаря
            const calendarBookings = [];
            response.data.calendar.forEach((emp) => {
                emp.bookings.forEach((slot) => {
                    if (Array.isArray(slot.bookings)) {
                        calendarBookings.push(...slot.bookings);
                    }
                });
            });

            bookings.value = calendarBookings;

            // Обновляем списки сотрудников и мест работы из ответа (уже отфильтрованные)
            if (response.data.employees) {
                employees.value = response.data.employees;
            }
            if (response.data.workplaces) {
                workplaces.value = response.data.workplaces;
            }

            // Обновляем словарь фильтров
            if (response.data.filter_dictionary) {
                filterDictionary.value = response.data.filter_dictionary;
            }

            return response.data;
        } catch (error) {
            console.error("Failed to fetch calendar:", error);
            throw error;
        } finally {
            loading.value = false;
        }
    }

    async function fetchReference() {
        try {
            const [
                workplacesRes,
                servicesRes,
                statusesRes,
                clientsRes,
                usersRes,
            ] = await Promise.all([
                window.axios.get("/api/workplaces"),
                window.axios.get("/api/services"),
                window.axios.get("/api/statuses"),
                window.axios.get("/api/clients"),
                window.axios.get("/api/users"),
            ]);

            workplaces.value = workplacesRes.data;
            services.value = servicesRes.data;
            statuses.value = statusesRes.data;
            clients.value = clientsRes.data.data || clientsRes.data;
            employees.value = usersRes.data.data || usersRes.data;
        } catch (error) {
            console.error("Failed to fetch references:", error);
            throw error;
        }
    }

    async function createBooking(bookingData) {
        try {
            const response = await window.axios.post(
                "/api/bookings",
                bookingData
            );
            bookings.value.push(response.data.booking);
            return response.data.booking;
        } catch (error) {
            throw error;
        }
    }

    async function updateBooking(id, bookingData) {
        try {
            const response = await window.axios.put(
                `/api/bookings/${id}`,
                bookingData
            );
            const index = bookings.value.findIndex((b) => b.id === id);
            if (index !== -1) {
                // Используем Object.assign для сохранения реактивности
                Object.assign(bookings.value[index], response.data.booking);
            }
            return response.data.booking;
        } catch (error) {
            throw error;
        }
    }

    async function moveBooking(id, moveData) {
        try {
            const response = await window.axios.post(
                `/api/bookings/${id}/move`,
                moveData
            );
            const index = bookings.value.findIndex((b) => b.id === id);
            if (index !== -1) {
                bookings.value[index] = response.data.booking;
            }
            return response.data.booking;
        } catch (error) {
            throw error;
        }
    }

    async function updateBookingStatus(id, statusId) {
        try {
            const response = await window.axios.post(
                `/api/bookings/${id}/status`,
                {
                    status_id: statusId,
                }
            );
            const index = bookings.value.findIndex((b) => b.id === id);
            if (index !== -1) {
                bookings.value[index].status = response.data.booking.status;
                bookings.value[index].status_id = statusId;
            }
            return response.data.booking;
        } catch (error) {
            throw error;
        }
    }

    async function updateAttendance(id, attended) {
        try {
            const response = await window.axios.post(
                `/api/bookings/${id}/attendance`,
                {
                    client_attended: attended,
                }
            );
            const index = bookings.value.findIndex((b) => b.id === id);
            if (index !== -1) {
                bookings.value[index].client_attended = attended;
            }
            return response.data.booking;
        } catch (error) {
            throw error;
        }
    }

    async function deleteBooking(id) {
        try {
            await window.axios.delete(`/api/bookings/${id}`);
            bookings.value = bookings.value.filter((b) => b.id !== id);
        } catch (error) {
            throw error;
        }
    }

    function addBooking(booking) {
        const exists = bookings.value.find((b) => b.id === booking.id);
        if (!exists) {
            bookings.value.push(booking);
        }
    }

    function updateBookingInStore(booking) {
        const index = bookings.value.findIndex((b) => b.id === booking.id);
        if (index !== -1) {
            bookings.value[index] = booking;
        }
    }

    function removeBooking(bookingId) {
        bookings.value = bookings.value.filter((b) => b.id !== bookingId);
    }

    function setView(view) {
        currentView.value = view;
    }

    function setDate(date) {
        currentDate.value = date;
    }

    function setFilters(filters) {
        if (filters.workplaceId !== undefined) {
            selectedWorkplaceId.value = filters.workplaceId;
        }
        if (filters.employeeId !== undefined) {
            selectedEmployeeId.value = filters.employeeId;
        }
        if (filters.statusId !== undefined) {
            selectedStatusId.value = filters.statusId;
        }
    }

    return {
        // State
        bookings,
        workplaces,
        employees,
        services,
        statuses,
        clients,
        loading,
        currentView,
        currentDate,
        selectedWorkplaceId,
        selectedEmployeeId,
        selectedStatusId,
        showCancelled, // Добавляем новое поле
        filterDictionary,

        // Computed
        filteredBookings,
        activeWorkplaces,
        activeServices,

        // Actions
        fetchCalendar,
        fetchReference,
        fetchReferenceData: fetchReference, // alias
        createBooking,
        updateBooking,
        moveBooking,
        updateBookingStatus,
        updateAttendance,
        deleteBooking,
        addBooking,
        updateBookingInStore,
        removeBooking,
        setView,
        setDate,
        setFilters,
    };
});
