<template>
    <div class="calendar-day-view">
        <div class="mb-4">
            <div v-for="workplace in groupedEmployees" :key="workplace.id" class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">{{ workplace.name }}</h3>
                
                <div class="grid gap-4" :style="{ gridTemplateColumns: `120px repeat(${workplace.employees.length}, minmax(200px, 1fr))` }">
                    <!-- Заголовок с временной шкалой -->
                    <div class="font-medium text-sm text-gray-600">Время</div>
                    <div v-for="employee in workplace.employees" :key="employee.id" class="text-center">
                        <div class="font-medium text-gray-800">{{ employee.name }}</div>
                        <div class="text-xs text-gray-500">{{ employee.email }}</div>
                    </div>

                    <!-- Временные слоты -->
                    <template v-for="hour in timeSlots" :key="hour">
                        <!-- Время -->
                        <div class="text-sm text-gray-600 py-2 border-t border-gray-200">
                            {{ hour }}:00
                        </div>
                        
                        <!-- Колонки для каждого сотрудника -->
                        <div
                            v-for="employee in workplace.employees"
                            :key="`${hour}-${employee.id}`"
                            :class="[
                                'border-t border-gray-200 min-h-20 p-1 relative',
                                isEmployee ? 'cursor-default' : 'cursor-pointer hover:bg-gray-50'
                            ]"
                            @click="createBookingAtSlot(employee.id, workplace.id, hour)"
                        >
                            <!-- Бронирования для этого слота -->
                            <div
                                v-for="booking in getBookingsForSlot(employee.id, hour)"
                                :key="booking.id"
                                class="booking-card mb-1 p-2 rounded text-xs cursor-pointer hover:shadow-lg transition"
                                :style="{ backgroundColor: booking.status?.color || '#e5e7eb' }"
                                @click.stop="openBooking(booking)"
                            >
                                <div class="font-medium text-gray-900">{{ booking.client?.name }}</div>
                                <div class="text-gray-700">
                                    {{ formatTime(booking.start_time) }} - {{ formatTime(booking.end_time) }}
                                </div>
                                <div class="text-gray-600">
                                    {{ booking.services?.map(s => s.name).join(', ') }}
                                </div>
                                <div class="text-gray-900 font-medium mt-1">
                                    {{ booking.total_price }} ₽
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Если нет данных -->
        <div v-if="groupedEmployees.length === 0" class="text-center py-12 text-gray-500">
            <p>Нет данных для отображения</p>
            <p class="text-sm mt-2">Выберите место работы или измените фильтры</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { format } from 'date-fns';
import { usePage } from '@inertiajs/vue3';
import { useCalendarStore } from '@/stores/calendar';
import { router } from '@inertiajs/vue3';

const page = usePage();
const calendarStore = useCalendarStore();

// Проверяем роль пользователя
const isEmployee = computed(() => page.props.auth.user?.is_employee);

// Временные слоты с 9:00 до 21:00
const timeSlots = Array.from({ length: 12 }, (_, i) => 9 + i);

// Группировка сотрудников по местам работы
const groupedEmployees = computed(() => {
    if (!calendarStore.workplaces || !calendarStore.employees) {
        return [];
    }

    // Если это обычный сотрудник, показываем только его самого
    if (isEmployee.value) {
        const currentUserId = page.props.auth.user.id;
        const currentEmployee = calendarStore.employees.find(e => e.id === currentUserId);
        
        if (!currentEmployee) {
            console.log('Сотрудник не найден в списке employees');
            return [];
        }
        
        console.log('Текущий сотрудник:', currentEmployee);
        console.log('Рабочие места сотрудника:', currentEmployee.workplaces);
        
        // У сотрудника может быть несколько рабочих мест (many-to-many связь)
        if (!currentEmployee.workplaces || currentEmployee.workplaces.length === 0) {
            console.log('У сотрудника нет рабочих мест');
            return [];
        }
        
        // Группируем по рабочим местам сотрудника
        return currentEmployee.workplaces.map(workplace => ({
            id: workplace.id,
            name: workplace.name,
            employees: [currentEmployee]
        }));
    }

    // Для администраторов показываем всех
    const groups = [];
    
    calendarStore.workplaces.forEach(workplace => {
        const employees = calendarStore.employees.filter(emp => 
            emp.workplaces?.some(w => w.id === workplace.id)
        );
        
        if (employees.length > 0) {
            groups.push({
                id: workplace.id,
                name: workplace.name,
                employees: employees
            });
        }
    });
    
    return groups;
});

// Получить бронирования для конкретного слота (сотрудник + час)
const getBookingsForSlot = (employeeId, hour) => {
    return calendarStore.bookings.filter(booking => {
        if (booking.employee_id !== employeeId) return false;
        
        const startHour = new Date(booking.start_time).getHours();
        return startHour === hour;
    });
};

// Форматирование времени
const formatTime = (datetime) => {
    return format(new Date(datetime), 'HH:mm');
};

// Создать бронирование в конкретном слоте
const createBookingAtSlot = (employeeId, workplaceId, hour) => {
    // Сотрудники не могут создавать брони
    if (isEmployee.value) {
        return;
    }
    
    if (window.openCreateBookingModal) {
        window.openCreateBookingModal({
            employeeId,
            workplaceId,
            date: format(calendarStore.currentDate, 'yyyy-MM-dd'),
            time: `${String(hour).padStart(2, '0')}:00`,
        });
    }
};

// Открыть существующее бронирование
const openBooking = (booking) => {
    if (window.openEditBookingModal) {
        window.openEditBookingModal(booking);
    }
};
</script>

<style scoped>
.booking-card {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
</style>
