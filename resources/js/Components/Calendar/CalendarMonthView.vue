<template>
    <div class="calendar-month-view">
        <!-- Заголовки дней недели -->
        <div class="grid grid-cols-7 gap-2 mb-2">
            <div v-for="day in ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']" :key="day" class="text-center font-medium text-sm text-gray-600 py-2">
                {{ day }}
            </div>
        </div>

        <!-- Календарная сетка -->
        <div class="grid grid-cols-7 gap-2">
            <div
                v-for="day in calendarDays"
                :key="day.dateStr"
                class="border border-gray-200 rounded min-h-[120px] p-2"
                :class="{
                    'bg-gray-50': !day.isCurrentMonth,
                    'bg-blue-50': day.isToday,
                    'bg-white': day.isCurrentMonth && !day.isToday,
                }"
            >
                <!-- Число месяца -->
                <div class="text-sm font-medium mb-1" :class="day.isCurrentMonth ? 'text-gray-800' : 'text-gray-400'">
                    {{ day.dayNumber }}
                </div>

                <!-- Бронирования на этот день -->
                <div class="space-y-1">
                    <div
                        v-for="booking in getBookingsForDay(day.date)"
                        :key="booking.id"
                        class="booking-card p-1 rounded text-xs cursor-pointer truncate"
                        :style="{ backgroundColor: booking.status?.color || '#e5e7eb' }"
                        @click="openBooking(booking)"
                    >
                        <div class="font-medium">{{ formatTime(booking.start_time) }}</div>
                        <div class="truncate">{{ booking.client?.name }}</div>
                        <div class="truncate text-gray-700">{{ booking.employee?.name }}</div>
                    </div>
                </div>

                <!-- Показать счетчик если много записей -->
                <div v-if="getBookingsForDay(day.date).length > 3" class="text-xs text-gray-600 mt-1">
                    +{{ getBookingsForDay(day.date).length - 3 }} ещё
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { format, startOfMonth, endOfMonth, startOfWeek, endOfWeek, eachDayOfInterval, isSameMonth, isToday as isTodayFn, isSameDay } from 'date-fns';
import { ru } from 'date-fns/locale';
import { useCalendarStore } from '@/stores/calendar';

const calendarStore = useCalendarStore();

// Генерация календарной сетки
const calendarDays = computed(() => {
    const monthStart = startOfMonth(calendarStore.currentDate);
    const monthEnd = endOfMonth(calendarStore.currentDate);
    const calendarStart = startOfWeek(monthStart, { weekStartsOn: 1 });
    const calendarEnd = endOfWeek(monthEnd, { weekStartsOn: 1 });

    const days = eachDayOfInterval({ start: calendarStart, end: calendarEnd });

    return days.map(date => ({
        date: date,
        dateStr: format(date, 'yyyy-MM-dd'),
        dayNumber: format(date, 'd'),
        isCurrentMonth: isSameMonth(date, calendarStore.currentDate),
        isToday: isTodayFn(date),
    }));
});

// Получить бронирования для конкретного дня
const getBookingsForDay = (date) => {
    return calendarStore.bookings
        .filter(booking => isSameDay(new Date(booking.start_time), date))
        .slice(0, 3); // Показываем только первые 3
};

const formatTime = (datetime) => {
    return format(new Date(datetime), 'HH:mm');
};

const openBooking = (booking) => {
    console.log('Open booking:', booking);
    // TODO: Открыть модальное окно редактирования
};
</script>

<style scoped>
.booking-card {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
</style>
