<template>
    <Head title="Календарь" />
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-4 md:p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <h1 class="text-xl md:text-2xl font-bold text-gray-900">Календарь бронирований</h1>
                    <button
                        v-if="!isEmployee"
                        @click="openCreateBookingModal({ date: format(new Date(), 'yyyy-MM-dd') })"
                        class="w-full sm:w-auto px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition text-sm md:text-base"
                    >
                        + Создать запись
                    </button>
                </div>

                <!-- View Switcher & Filters -->
                <div class="mt-4 md:mt-6 flex flex-col sm:flex-row flex-wrap gap-3 md:gap-4 items-stretch sm:items-center">
                    <!-- View Mode (скрыт для сотрудников) -->
                    <div v-if="!isEmployee" class="flex rounded-lg shadow-sm">
                        <button
                            v-for="view in ['day', 'week', 'month']"
                            :key="view"
                            @click="calendarStore.currentView = view"
                            :class="[
                                calendarStore.currentView === view
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-gray-700 hover:bg-gray-50',
                                'flex-1 sm:flex-none px-3 md:px-4 py-2 text-xs md:text-sm font-medium first:rounded-l-lg last:rounded-r-lg border border-gray-300'
                            ]"
                        >
                            {{ viewLabels[view] }}
                        </button>
                    </div>

                    <!-- Date Navigation -->
                    <div class="flex items-center justify-center space-x-1 md:space-x-2">
                        <button @click="previousPeriod" class="p-2 rounded hover:bg-gray-100 text-lg">
                            ←
                        </button>
                        <span class="text-xs md:text-sm font-medium text-gray-700 min-w-[150px] md:min-w-[200px] text-center">
                            {{ currentPeriodLabel }}
                        </span>
                        <button @click="nextPeriod" class="p-2 rounded hover:bg-gray-100 text-lg">
                            →
                        </button>
                        <button @click="goToday" class="ml-1 md:ml-2 px-2 md:px-3 py-1 text-xs md:text-sm bg-gray-100 rounded hover:bg-gray-200">
                            Сегодня
                        </button>
                    </div>

                    <!-- Filters (скрыты для сотрудников) -->
                    <div v-if="!isEmployee" class="w-full sm:w-auto flex flex-col sm:flex-row gap-2">
                        <select
                            v-model="calendarStore.selectedWorkplaceId"
                            class="text-xs md:text-sm border-gray-300 rounded-lg"
                        >
                            <option :value="null">Все места работы</option>
                            <option 
                                v-for="workplace in calendarStore.filterDictionary.workplaces" 
                                :key="workplace.id" 
                                :value="workplace.id"
                            >
                                {{ workplace.name }}
                            </option>
                        </select>

                        <select
                            v-model="calendarStore.selectedEmployeeId"
                            class="text-xs md:text-sm border-gray-300 rounded-lg"
                        >
                            <option :value="null">Все сотрудники</option>
                            <option 
                                v-for="employee in calendarStore.filterDictionary.employees" 
                                :key="employee.id" 
                                :value="employee.id"
                            >
                                {{ employee.name }}
                            </option>
                        </select>

                        <select
                            v-model="calendarStore.selectedStatusId"
                            class="text-xs md:text-sm border-gray-300 rounded-lg"
                        >
                            <option :value="null">Все статусы</option>
                            <option 
                                v-for="status in calendarStore.filterDictionary.statuses" 
                                :key="status.id" 
                                :value="status.id"
                            >
                                {{ status.name }}
                            </option>
                        </select>

                        <!-- Переключатель отображения отменённых бронирований -->
                        <label class="flex items-center gap-2 px-3 py-2 text-xs md:text-sm bg-gray-50 rounded-lg border border-gray-300 cursor-pointer hover:bg-gray-100 whitespace-nowrap">
                            <input 
                                type="checkbox" 
                                v-model="calendarStore.showCancelled"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-gray-700">Показать отменённые</span>
                        </label>
                        
                        <!-- Кнопка сброса фильтров -->
                        <button
                            v-if="hasActiveFilters"
                            @click="resetFilters"
                            class="px-3 py-2 text-xs md:text-sm bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 whitespace-nowrap"
                        >
                            ✕ Сбросить фильтры
                        </button>
                    </div>
                </div>
            </div>

            <!-- Calendar Content -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-3 md:p-6">
                    <div v-if="loading" class="text-center py-12">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                        <p class="mt-2 text-sm md:text-base text-gray-600">Загрузка...</p>
                    </div>
                    <div v-else>
                        <CalendarDayView v-if="calendarStore.currentView === 'day'" />
                        <CalendarWeekView v-else-if="calendarStore.currentView === 'week'" />
                        <CalendarMonthView v-else />
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Modal -->
        <BookingModal
            :show="showBookingModal"
            :booking="selectedBooking"
            :initial-data="bookingInitialData"
            @close="closeBookingModal"
            @saved="handleBookingSaved"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { format, addDays, addWeeks, addMonths, startOfDay, startOfWeek, startOfMonth } from 'date-fns';
import { ru } from 'date-fns/locale';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CalendarDayView from '@/Components/Calendar/CalendarDayView.vue';
import CalendarWeekView from '@/Components/Calendar/CalendarWeekView.vue';
import CalendarMonthView from '@/Components/Calendar/CalendarMonthView.vue';
import BookingModal from '@/Components/Modals/BookingModal.vue';
import { useCalendarStore } from '@/stores/calendar';

const page = usePage();
const calendarStore = useCalendarStore();
const loading = ref(true);
const showBookingModal = ref(false);
const selectedBooking = ref(null);
const bookingInitialData = ref(null);

// WebSocket channel
let channel = null;

// Проверяем роль пользователя
const isEmployee = computed(() => page.props.auth.user?.is_employee);

// Получаем tenant ID для WebSocket
const tenantId = computed(() => page.props.auth.tenant?.id);

// Проверяем наличие активных фильтров
const hasActiveFilters = computed(() => {
    return calendarStore.selectedWorkplaceId !== null ||
           calendarStore.selectedEmployeeId !== null ||
           calendarStore.selectedStatusId !== null;
});

// Сброс всех фильтров
const resetFilters = () => {
    calendarStore.selectedWorkplaceId = null;
    calendarStore.selectedEmployeeId = null;
    calendarStore.selectedStatusId = null;
    calendarStore.showCancelled = false;
};

const viewLabels = {
    day: 'День',
    week: 'Неделя',
    month: 'Месяц',
};

const currentPeriodLabel = computed(() => {
    const date = calendarStore.currentDate;
    if (calendarStore.currentView === 'day') {
        return format(date, 'd MMMM yyyy', { locale: ru });
    } else if (calendarStore.currentView === 'week') {
        const weekStart = startOfWeek(date, { weekStartsOn: 1 });
        return format(weekStart, 'd MMMM yyyy', { locale: ru });
    } else {
        return format(date, 'LLLL yyyy', { locale: ru });
    }
});

const previousPeriod = () => {
    if (calendarStore.currentView === 'day') {
        calendarStore.currentDate = addDays(calendarStore.currentDate, -1);
    } else if (calendarStore.currentView === 'week') {
        calendarStore.currentDate = addWeeks(calendarStore.currentDate, -1);
    } else {
        calendarStore.currentDate = addMonths(calendarStore.currentDate, -1);
    }
};

const nextPeriod = () => {
    if (calendarStore.currentView === 'day') {
        calendarStore.currentDate = addDays(calendarStore.currentDate, 1);
    } else if (calendarStore.currentView === 'week') {
        calendarStore.currentDate = addWeeks(calendarStore.currentDate, 1);
    } else {
        calendarStore.currentDate = addMonths(calendarStore.currentDate, 1);
    }
};

const goToday = () => {
    calendarStore.currentDate = new Date();
};

const openCreateBookingModal = (initialData = null) => {
    selectedBooking.value = null;
    bookingInitialData.value = initialData;
    showBookingModal.value = true;
};

const openEditBookingModal = (booking) => {
    selectedBooking.value = booking;
    bookingInitialData.value = null;
    showBookingModal.value = true;
};

const closeBookingModal = () => {
    showBookingModal.value = false;
    selectedBooking.value = null;
    bookingInitialData.value = null;
};

const handleBookingSaved = async () => {
    await calendarStore.fetchCalendar();
    await calendarStore.fetchReferenceData();
};

// Делаем функции доступными глобально для вызова из дочерних компонентов
window.openCreateBookingModal = openCreateBookingModal;
window.openEditBookingModal = openEditBookingModal;

const loadData = async () => {
    loading.value = true;
    try {
        await Promise.all([
            calendarStore.fetchCalendar(),
            calendarStore.fetchReferenceData(),
        ]);
    } finally {
        loading.value = false;
    }
};

// Загрузка данных при монтировании
onMounted(async () => {
    // Для сотрудников устанавливаем вид "день" и фильтр на их ID
    if (isEmployee.value) {
        calendarStore.currentView = 'day';
        calendarStore.currentDate = new Date(); // Текущий день
        calendarStore.selectedEmployeeId = page.props.auth.user.id;
    }
    
    await loadData();
    
    // Подключаемся к WebSocket каналу кабинета
    if (window.Echo && tenantId.value) {
        console.log(`Подключаюсь к каналу tenant.${tenantId.value}`);
        
        // Используем private channel
        channel = window.Echo.private(`tenant.${tenantId.value}`)
            .listen('.booking.created', (event) => {
                console.log('📅 Новая бронь создана:', event.booking);
                calendarStore.addBooking(event.booking);
            })
            .listen('.booking.updated', (event) => {
                console.log('✏️ Бронь обновлена:', event.booking);
                calendarStore.updateBookingInStore(event.booking);
            })
            .listen('.booking.deleted', (event) => {
                console.log('🗑️ Бронь удалена:', event.booking_id);
                calendarStore.removeBooking(event.booking_id);
            })
            .error((error) => {
                console.error('WebSocket error:', error);
            });
    } else {
        if (!window.Echo) {
            console.warn('Laravel Echo не инициализирован');
        }
        if (!tenantId.value) {
            console.warn('Tenant ID не найден');
        }
    }
});

// Отключаемся от канала при размонтировании
onUnmounted(() => {
    if (channel && typeof channel.leave === 'function') {
        console.log('Отключаюсь от WebSocket канала');
        channel.leave();
        channel = null;
    }
});

// Перезагрузка при изменении даты или вида
watch(
    () => [calendarStore.currentView, calendarStore.currentDate],
    () => {
        calendarStore.fetchCalendar();
    }
);

// Перезагрузка при изменении фильтров
watch(
    () => [
        calendarStore.selectedWorkplaceId,
        calendarStore.selectedEmployeeId,
        calendarStore.selectedStatusId,
        calendarStore.showCancelled,
    ],
    () => {
        calendarStore.fetchCalendar();
    }
);
</script>
