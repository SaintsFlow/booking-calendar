<template>
    <Modal :show="show" @close="close" :title="isEdit ? 'Редактировать бронирование' : 'Новое бронирование'" max-width="4xl">
        <form @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-6">
                <!-- Левая колонка -->
                <div class="space-y-4">
                    <!-- Поиск клиента -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Клиент *</label>
                        <Combobox v-model="form.client_id" nullable>
                            <div class="relative">
                                <ComboboxInput
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    :display-value="(clientId) => clients.find(c => c.id === clientId)?.full_name || ''"
                                    @change="clientQuery = $event.target.value"
                                    placeholder="Начните вводить имя или телефон..."
                                />
                                <ComboboxButton class="absolute inset-y-0 right-0 flex items-center pr-2">
                                    <ChevronUpDownIcon class="h-5 w-5 text-gray-400" />
                                </ComboboxButton>
                                <ComboboxOptions class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                    <ComboboxOption
                                        v-for="client in filteredClients"
                                        :key="client.id"
                                        :value="client.id"
                                        v-slot="{ active, selected }"
                                    >
                                        <li :class="['px-4 py-2 cursor-pointer', active ? 'bg-indigo-600 text-white' : 'text-gray-900']">
                                            <span :class="['block truncate', selected ? 'font-medium' : 'font-normal']">
                                                {{ client.full_name }}
                                            </span>
                                            <span :class="['block text-sm', active ? 'text-indigo-200' : 'text-gray-500']">
                                                {{ client.phone }}
                                            </span>
                                        </li>
                                    </ComboboxOption>
                                    <div v-if="filteredClients.length === 0" class="px-4 py-2 text-gray-500">
                                        Клиент не найден
                                    </div>
                                </ComboboxOptions>
                            </div>
                        </Combobox>
                        <p v-if="getError('client_id')" class="mt-1 text-sm text-red-600">{{ getError('client_id') }}</p>
                    </div>

                    <!-- Место работы -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Место работы *</label>
                        <select 
                            v-model="form.workplace_id" 
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                            required
                        >
                            <option value="">Выберите место</option>
                            <option v-for="workplace in workplaces" :key="workplace.id" :value="workplace.id">
                                {{ workplace.name }}
                            </option>
                        </select>
                        <p v-if="getError('workplace_id')" class="mt-1 text-sm text-red-600">{{ getError('workplace_id') }}</p>
                    </div>

                    <!-- Сотрудник -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Сотрудник *</label>
                        <select 
                            v-model="form.employee_id" 
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                            required
                        >
                            <option value="">Выберите сотрудника</option>
                            <option v-for="user in employees" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                        <p v-if="getError('employee_id')" class="mt-1 text-sm text-red-600">{{ getError('employee_id') }}</p>
                    </div>

                    <!-- Дата и время -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Дата *</label>
                            <input 
                                type="date" 
                                v-model="form.date" 
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                                required 
                            />
                            <p v-if="getError('start_time')" class="mt-1 text-sm text-red-600">{{ getError('start_time') }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Время *</label>
                            <input 
                                type="time" 
                                v-model="form.time" 
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                                required 
                            />
                        </div>
                    </div>

                    <!-- Информация о рабочих часах -->
                    <div v-if="loadingSchedule" class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <div class="flex items-center">
                            <svg class="animate-spin h-4 w-4 text-gray-600 mr-2" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm text-gray-600">Загрузка расписания...</span>
                        </div>
                    </div>
                    
                    <div v-else-if="form.employee_id && form.date">
                        <!-- Сотрудник не работает -->
                        <div v-if="workingHours === null" class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-amber-600 mr-2 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-amber-800">Сотрудник не работает в этот день</p>
                                    <p class="text-sm text-amber-700 mt-1">Выберите другую дату или другого сотрудника</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Рабочие часы -->
                        <div v-else-if="workingHours" class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-600 mr-2 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                </svg>
                                <div class="flex-1">
                                    <p class="text-sm text-green-800">
                                        Рабочие часы: <span class="font-semibold">{{ workingHours.start }} - {{ workingHours.end }}</span>
                                    </p>
                                    <p v-if="availableSlots.length > 0" class="text-xs text-green-700 mt-1">
                                        Доступно слотов: {{ availableSlots.length }}
                                    </p>
                                    <p v-else-if="totalDuration" class="text-xs text-amber-700 mt-1">
                                        Нет доступных слотов на выбранную дату
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Предупреждение о конфликте времени -->
                    <div v-if="timeConflict" class="bg-red-50 border border-red-200 rounded-lg p-3">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-red-600 mr-2 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-red-800">Время занято!</p>
                                <p class="text-sm text-red-700 mt-1">
                                    В это время уже запланирована запись: {{ timeConflict.conflict.start_time }} - {{ timeConflict.conflict.end_time }}
                                    ({{ timeConflict.conflict.client_name }})
                                </p>
                                <button
                                    v-if="firstAvailableSlot"
                                    @click="useFirstAvailableSlot"
                                    type="button"
                                    class="mt-2 text-sm text-red-600 hover:text-red-800 font-medium underline"
                                >
                                    Использовать первое доступное время: {{ firstAvailableSlot }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Подсказка о первом доступном слоте -->
                    <div v-else-if="firstAvailableSlot && form.employee_id && form.workplace_id && totalDuration" class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-blue-600 mr-2 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm text-blue-800">
                                    Первое доступное время для записи на {{ formatDuration(totalDuration) }}: <span class="font-semibold">{{ firstAvailableSlot }}</span>
                                </p>
                                <button
                                    @click="useFirstAvailableSlot"
                                    type="button"
                                    class="mt-1 text-sm text-blue-600 hover:text-blue-800 font-medium underline"
                                >
                                    Использовать это время
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Статус -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Статус *</label>
                        <select 
                            v-model="form.status_id" 
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                            required
                        >
                            <option value="">Выберите статус</option>
                            <option v-for="status in statuses" :key="status.id" :value="status.id">
                                {{ status.name }}
                            </option>
                        </select>
                        <p v-if="getError('status_id')" class="mt-1 text-sm text-red-600">{{ getError('status_id') }}</p>
                    </div>
                </div>

                <!-- Правая колонка -->
                <div class="space-y-4">
                    <!-- Услуги -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Услуги *</label>
                        <div class="space-y-2 max-h-64 overflow-y-auto border border-gray-200 rounded-lg p-3">
                            <label v-for="service in services" :key="service.id" class="flex items-start space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer">
                                <input
                                    type="checkbox"
                                    :value="service.id"
                                    v-model="form.service_ids"
                                    class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">{{ service.name }}</div>
                                    <div class="text-sm text-gray-500">{{ formatDuration(service.duration) }} • {{ service.price }} ₽</div>
                                </div>
                            </label>
                        </div>
                        <p v-if="getError('service_ids')" class="mt-1 text-sm text-red-600">{{ getError('service_ids') }}</p>
                    </div>

                    <!-- Автоматический расчет -->
                    <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Длительность:</span>
                            <span class="font-medium">{{ formatDuration(totalDuration) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Стоимость:</span>
                            <span class="font-medium">{{ totalPrice }} ₽</span>
                        </div>
                        <div class="flex justify-between text-sm border-t pt-2">
                            <span class="text-gray-600">Окончание:</span>
                            <span class="font-medium">{{ endTime }}</span>
                        </div>
                    </div>

                    <!-- Примечания -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Примечания</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Дополнительная информация..."
                        ></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" @click="close" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Отмена
                </button>
                <button type="submit" :disabled="processing" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                    {{ processing ? 'Сохранение...' : (isEdit ? 'Сохранить' : 'Создать') }}
                </button>
            </div>
        </form>
    </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue'
import { ChevronUpDownIcon } from '@heroicons/vue/20/solid'
import Modal from '../Modal.vue'
import axios from 'axios'
import { useCalendarStore } from '@/stores/calendar'
import { useToast } from '@/composables/useToast'

const props = defineProps({
    show: Boolean,
    booking: Object,
    initialData: Object,
})

const emit = defineEmits(['close', 'saved'])

const toast = useToast()

const calendarStore = useCalendarStore()

const form = ref({
    client_id: '',
    workplace_id: '',
    employee_id: '',
    date: '',
    time: '',
    status_id: '',
    service_ids: [],
    notes: '',
})

const errors = ref({})
const processing = ref(false)
const clientQuery = ref('')
const timeConflict = ref(null)
const checkingConflict = ref(false)
const firstAvailableSlot = ref(null)
const workingHours = ref(null)
const availableSlots = ref([])
const loadingSchedule = ref(false)

const isEdit = computed(() => !!props.booking?.id)

// Helper для форматирования ошибок (они приходят как массив)
const getError = (field) => {
    const error = errors.value[field]
    if (!error) return null
    return Array.isArray(error) ? error[0] : error
}

const clients = computed(() => calendarStore.clients || [])
const workplaces = computed(() => calendarStore.workplaces || [])
const employees = computed(() => (calendarStore.employees || []).filter(u => u.role === 'employee' || u.role === 'admin' || u.role === 'manager'))
const services = computed(() => calendarStore.services || [])
const statuses = computed(() => calendarStore.statuses || [])

const filteredClients = computed(() => {
    const clientsList = clients.value || []
    if (!clientQuery.value) return clientsList

    const query = clientQuery.value.toLowerCase()
    return clientsList.filter(client => 
        client.full_name?.toLowerCase().includes(query) ||
        client.phone?.toLowerCase().includes(query)
    )
})

const selectedServices = computed(() => 
    services.value.filter(s => form.value.service_ids.includes(s.id))
)

const totalDuration = computed(() => {
    const duration = selectedServices.value.reduce((sum, s) => sum + (parseInt(s.duration) || 0), 0)
    return duration
})

const totalPrice = computed(() => {
    const price = selectedServices.value.reduce((sum, s) => sum + (parseFloat(s.price) || 0), 0)
    return price.toFixed(2)
})

const endTime = computed(() => {
    if (!form.value.time || !totalDuration.value) return '--:--'
    
    const [hours, minutes] = form.value.time.split(':').map(Number)
    const totalMinutes = hours * 60 + minutes + totalDuration.value
    const endHours = Math.floor(totalMinutes / 60) % 24
    const endMinutes = totalMinutes % 60
    
    return `${String(endHours).padStart(2, '0')}:${String(endMinutes).padStart(2, '0')}`
})

// Форматирование длительности (минуты → "X ч Y мин")
const formatDuration = (minutes) => {
    if (!minutes) return '0 мин'
    const hours = Math.floor(minutes / 60)
    const mins = minutes % 60
    if (hours === 0) return `${mins} мин`
    if (mins === 0) return `${hours} ч`
    return `${hours} ч ${mins} мин`
}

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.booking) {
            // Редактирование - используем start_time вместо start_at
            const startTime = new Date(props.booking.start_time)
            form.value = {
                client_id: props.booking.client?.id || props.booking.client_id,
                workplace_id: props.booking.workplace?.id || props.booking.workplace_id,
                employee_id: props.booking.employee?.id || props.booking.employee_id,
                date: startTime.toISOString().split('T')[0],
                time: startTime.toTimeString().slice(0, 5),
                status_id: props.booking.status?.id || props.booking.status_id,
                service_ids: props.booking.services?.map(s => s.id) || [],
                notes: props.booking.comment || '',
            }
        } else if (props.initialData) {
            // Создание с начальными данными
            // Если date уже строка формата YYYY-MM-DD, используем её напрямую
            const dateStr = typeof props.initialData.date === 'string' 
                ? props.initialData.date 
                : new Date().toISOString().split('T')[0]
            form.value = {
                client_id: '',
                workplace_id: props.initialData.workplaceId || '',
                employee_id: props.initialData.employeeId || '',
                date: dateStr,
                time: props.initialData.time || '09:00',
                status_id: statuses.value.find(s => s.slug === 'confirmed')?.id || '',
                service_ids: [],
                notes: '',
            }
        } else {
            // Пустая форма
            resetForm()
        }
        errors.value = {}
    }
})

const resetForm = () => {
    form.value = {
        client_id: '',
        workplace_id: '',
        employee_id: '',
        date: new Date().toISOString().split('T')[0],
        time: '09:00',
        status_id: statuses.value.find(s => s.slug === 'confirmed')?.id || '',
        service_ids: [],
        notes: '',
    }
    timeConflict.value = null
    firstAvailableSlot.value = null
}

// Проверка конфликта времени
const checkTimeConflict = async () => {
    if (!form.value.employee_id || !form.value.workplace_id || !form.value.date || !form.value.time || !totalDuration.value) {
        timeConflict.value = null
        return
    }

    checkingConflict.value = true
    
    try {
        const response = await axios.post('/api/calendar/check-conflict', {
            employee_id: form.value.employee_id,
            workplace_id: form.value.workplace_id,
            date: form.value.date,
            time: form.value.time,
            duration_minutes: totalDuration.value,
            exclude_booking_id: props.booking?.id
        })
        
        timeConflict.value = response.data.has_conflict ? response.data : null
    } catch (error) {
        console.error('Error checking conflict:', error)
    } finally {
        checkingConflict.value = false
    }
}

// Получение первого доступного слота
const fetchFirstAvailableSlot = async () => {
    if (!form.value.employee_id || !form.value.date || !totalDuration.value) {
        firstAvailableSlot.value = null
        availableSlots.value = []
        return
    }

    loadingSchedule.value = true

    try {
        const response = await axios.get('/api/schedule/available-slots', {
            params: {
                employee_id: form.value.employee_id,
                date: form.value.date,
                duration_minutes: totalDuration.value,
                exclude_booking_id: props.booking?.id
            }
        })
        
        availableSlots.value = response.data.available_slots || []
        firstAvailableSlot.value = availableSlots.value[0] || null
    } catch (error) {
        console.error('Error fetching available slots:', error)
        firstAvailableSlot.value = null
        availableSlots.value = []
    } finally {
        loadingSchedule.value = false
    }
}

// Получение рабочих часов сотрудника
const fetchWorkingHours = async () => {
    if (!form.value.employee_id || !form.value.date) {
        workingHours.value = null
        return
    }

    try {
        const response = await axios.get('/api/schedule/available-hours', {
            params: {
                employee_id: form.value.employee_id,
                date: form.value.date,
                workplace_id: form.value.workplace_id || null
            }
        })
        
        workingHours.value = response.data.working_hours
    } catch (error) {
        console.error('Error fetching working hours:', error)
        workingHours.value = null
    }
}

// Использовать первый доступный слот
const useFirstAvailableSlot = () => {
    if (firstAvailableSlot.value) {
        form.value.time = firstAvailableSlot.value
        timeConflict.value = null
    }
}

// Watch для автоматической проверки конфликтов
watch([
    () => form.value.employee_id,
    () => form.value.workplace_id,
    () => form.value.date,
    () => form.value.time,
    () => totalDuration.value
], () => {
    checkTimeConflict()
}, { deep: true })

// Watch для получения рабочих часов и доступных слотов
watch([
    () => form.value.employee_id,
    () => form.value.workplace_id,
    () => form.value.date,
], () => {
    fetchWorkingHours()
}, { deep: true })

watch([
    () => form.value.employee_id,
    () => form.value.date,
    () => totalDuration.value
], () => {
    fetchFirstAvailableSlot()
}, { deep: true })

const submit = async () => {
    if (processing.value) return
    
    // Проверяем конфликт перед отправкой
    if (timeConflict.value) {
        toast.error(
            'Невозможно создать запись',
            'Выбранное время занято. Выберите другое время или используйте первое доступное.'
        )
        return
    }
    
    // Проверяем, что время не в прошлом (только для новых записей)
    if (!isEdit.value) {
        const [hours, minutes] = form.value.time.split(':')
        const [year, month, day] = form.value.date.split('-').map(Number)
        const startDateTime = new Date(year, month - 1, day, parseInt(hours), parseInt(minutes), 0, 0)
        
        if (startDateTime < new Date()) {
            toast.error(
                'Неверное время',
                'Нельзя создать запись в прошлом. Выберите будущее время.'
            )
            return
        }
    }
    
    processing.value = true
    errors.value = {}

    try {
        // Создаём правильный Date объект в локальном timezone
        const [hours, minutes] = form.value.time.split(':')
        // Парсим дату правильно (YYYY-MM-DD)
        const [year, month, day] = form.value.date.split('-').map(Number)
        const startDateTime = new Date(year, month - 1, day, parseInt(hours), parseInt(minutes), 0, 0)
        
        const data = {
            client_id: form.value.client_id,
            workplace_id: form.value.workplace_id,
            employee_id: form.value.employee_id,
            status_id: form.value.status_id,
            service_ids: form.value.service_ids,
            start_time: startDateTime.toISOString(),
            comment: form.value.notes || null,
        }

        if (isEdit.value) {
            await calendarStore.updateBooking(props.booking.id, data)
            toast.success('Запись обновлена', 'Изменения успешно сохранены')
        } else {
            await calendarStore.createBooking(data)
            toast.success('Запись создана', 'Новая запись успешно добавлена в календарь')
        }

        emit('saved')
        close()
    } catch (error) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors
            toast.error('Ошибка валидации', 'Проверьте правильность заполнения полей')
        } else {
            toast.error(
                'Произошла ошибка',
                error.response?.data?.message || 'Не удалось сохранить запись'
            )
        }
    } finally {
        processing.value = false
    }
}

const close = () => {
    emit('close')
}
</script>
