<template>
    <div class="calendar-week-view overflow-x-auto">
        <div class="min-w-[1000px]">
            <!-- Заголовки дней недели -->
            <div class="grid grid-cols-8 gap-2 mb-4">
                <div class="font-medium text-sm text-gray-600">Сотрудник</div>
                <div
                    v-for="day in weekDays"
                    :key="day.date"
                    class="text-center"
                    :class="{ 'bg-blue-50 rounded': isToday(day.date) }"
                >
                    <div class="font-medium text-gray-800">{{ day.dayName }}</div>
                    <div class="text-sm text-gray-600">{{ day.dateStr }}</div>
                </div>
            </div>

            <!-- Сообщение "Нет результатов" -->
            <div v-if="groupedEmployees.length === 0" class="text-center py-12">
                <p class="text-gray-500 text-lg">Нет результатов по выбранным фильтрам</p>
            </div>

            <!-- Строки с сотрудниками -->
            <div v-for="workplace in groupedEmployees" :key="workplace.id" class="mb-6">
                <h3 class="text-md font-semibold text-gray-700 mb-2">{{ workplace.name }}</h3>
                
                <div v-for="employee in workplace.employees" :key="employee.id" class="grid grid-cols-8 gap-2 mb-2">
                    <!-- Имя сотрудника -->
                    <div class="font-medium text-sm text-gray-700 py-2">
                        {{ employee.name }}
                    </div>
                    
                    <!-- Ячейки дней -->
                    <div
                        v-for="day in weekDays"
                        :key="`${employee.id}-${day.date}`"
                        :class="[
                            'border border-gray-200 rounded min-h-[100px] p-1 bg-white relative',
                            isEmployee ? 'cursor-default' : 'hover:bg-gray-50 cursor-pointer'
                        ]"
                        @click="createBooking(employee.id, workplace.id, day.date)"
                        @drop="onDrop($event, employee.id, workplace.id, day.date)"
                        @dragover.prevent
                        @dragenter.prevent="onDragEnter($event)"
                        @dragleave.prevent="onDragLeave($event)"
                    >
                        <div
                            v-for="booking in getBookingsForDay(employee.id, day.date)"
                            :key="booking.id"
                            class="booking-card mb-1 p-1 rounded text-xs cursor-move"
                            :style="{ backgroundColor: booking.status?.color || '#e5e7eb' }"
                            draggable="true"
                            @dragstart="onDragStart($event, booking)"
                            @dragend="onDragEnd($event)"
                            @click.stop="openBooking(booking)"
                        >
                            <div class="font-medium">{{ formatTime(booking.start_time) }}</div>
                            <div class="truncate">{{ booking.client?.name }}</div>
                            <div class="text-gray-700">{{ booking.total_price }} ₽</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useCalendarStore } from '@/stores/calendar';
import { format, addDays, startOfWeek, isSameDay, isToday as isTodayFn } from 'date-fns';
import { ru } from 'date-fns/locale';
import { useToast } from '@/composables/useToast';

const page = usePage();
const calendarStore = useCalendarStore();
const toast = useToast();
const draggedBooking = ref(null);

// Проверяем роль пользователя
const isEmployee = computed(() => page.props.auth.user?.is_employee);

// Дни недели (7 дней начиная с понедельника)
const weekDays = computed(() => {
    const weekStart = startOfWeek(calendarStore.currentDate, { weekStartsOn: 1 });
    return Array.from({ length: 7 }, (_, i) => {
        const date = addDays(weekStart, i);
        return {
            date: date,
            dayName: format(date, 'EEEEEE', { locale: ru }), // Пн, Вт, Ср...
            dateStr: format(date, 'd MMM', { locale: ru }),
        };
    });
});

// Группировка сотрудников по местам работы
const groupedEmployees = computed(() => {
    const groups = [];
    const addedEmployeeIds = new Set();
    
    // Данные уже отфильтрованы на бэкенде, просто группируем
    const workplacesToShow = calendarStore.workplaces;
    const employeesToShow = calendarStore.employees;
    
    // Добавляем сотрудников с назначенными местами работы
    workplacesToShow.forEach(workplace => {
        const employees = employeesToShow.filter(emp => 
            emp.workplaces?.some(w => w.id === workplace.id)
        );
        
        if (employees.length > 0) {
            groups.push({
                id: workplace.id,
                name: workplace.name,
                employees: employees
            });
            
            // Запоминаем, каких сотрудников уже добавили
            employees.forEach(emp => addedEmployeeIds.add(emp.id));
        }
    });
    
    // Добавляем сотрудников, которые не попали ни в одну группу
    const employeesWithoutGroup = employeesToShow.filter(emp => 
        !addedEmployeeIds.has(emp.id)
    );
    
    if (employeesWithoutGroup.length > 0) {
        groups.push({
            id: null,
            name: 'Без назначенного места работы',
            employees: employeesWithoutGroup
        });
    }
    
    return groups;
});

// Получить бронирования для конкретного дня и сотрудника
// Показываем ВСЕ брони сотрудника, независимо от workplace_id
const getBookingsForDay = (employeeId, date) => {
    return calendarStore.bookings.filter(booking => {
        if (booking.employee_id !== employeeId) return false;
        return isSameDay(new Date(booking.start_time), date);
    });
};

const isToday = (date) => {
    return isTodayFn(date);
};

const formatTime = (datetime) => {
    return format(new Date(datetime), 'HH:mm');
};

const createBooking = (employeeId, workplaceId, date) => {
    // Сотрудники не могут создавать брони
    if (isEmployee.value) {
        return;
    }
    
    if (window.openCreateBookingModal) {
        window.openCreateBookingModal({
            employeeId,
            workplaceId,
            date: format(date, 'yyyy-MM-dd'),
            time: '09:00',
        });
    }
};

const openBooking = (booking) => {
    if (window.openEditBookingModal) {
        window.openEditBookingModal(booking);
    }
};

// Drag & Drop handlers
const onDragStart = (event, booking) => {
    draggedBooking.value = booking;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('booking', JSON.stringify(booking));
    event.target.style.opacity = '0.5';
    event.target.classList.add('dragging');
};

const onDragEnd = (event) => {
    event.target.style.opacity = '1';
    event.target.classList.remove('dragging');
};

const onDragEnter = (event) => {
    if (event.target.classList.contains('border')) {
        event.target.classList.add('bg-blue-100', 'border-blue-400');
    }
};

const onDragLeave = (event) => {
    event.target.classList.remove('bg-blue-100', 'border-blue-400');
};

const onDrop = async (event, employeeId, workplaceId, date) => {
    event.preventDefault();
    event.target.classList.remove('bg-blue-100', 'border-blue-400');
    
    if (!draggedBooking.value) return;
    
    // Формируем новое время начала (берём время из исходного бронирования)
    const originalTime = new Date(draggedBooking.value.start_time);
    
    // Правильно парсим дату (date это Date объект из weekDays)
    const newDate = new Date(date);
    newDate.setHours(originalTime.getHours(), originalTime.getMinutes(), 0, 0);
    
    // Проверяем конфликт перед перемещением
    try {
        const conflictCheck = await window.axios.post('/api/calendar/check-conflict', {
            employee_id: employeeId,
            workplace_id: workplaceId,
            date: format(newDate, 'yyyy-MM-dd'),
            time: format(newDate, 'HH:mm'),
            duration_minutes: draggedBooking.value.duration_minutes,
            exclude_booking_id: draggedBooking.value.id
        });
        
        if (conflictCheck.data.has_conflict) {
            toast.error('Конфликт времени', conflictCheck.data.message);
            draggedBooking.value = null;
            return;
        }
        
        // Перемещаем бронирование
        await window.axios.post(`/api/bookings/${draggedBooking.value.id}/move`, {
            start_time: newDate.toISOString(),
            employee_id: employeeId,
            workplace_id: workplaceId
        });
        
        toast.success('Запись перемещена', 'Бронирование успешно перенесено');
        
        // Обновляем календарь
        await calendarStore.fetchCalendar();
        
    } catch (error) {
        const errorMsg = error.response?.data?.message || 'Не удалось переместить запись';
        toast.error('Ошибка', errorMsg);
        console.error('Drag & drop error:', error);
    } finally {
        draggedBooking.value = null;
    }
};
</script>

<style scoped>
.booking-card {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
</style>
