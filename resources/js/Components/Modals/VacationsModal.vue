<template>
    <Modal :show="show" @close="close" title="Управление отпусками" max-width="4xl">
        <div class="space-y-6">
            <!-- Заголовок -->
            <div>
                <h3 class="text-lg font-medium text-gray-900">{{ employee?.name }}</h3>
                <p class="mt-1 text-sm text-gray-500">Управление отпусками и выходными сотрудника</p>
            </div>

            <!-- Форма добавления нового отпуска -->
            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Добавить отпуск</h4>
                <form @submit.prevent="addVacation" class="grid grid-cols-12 gap-3">
                    <div class="col-span-3">
                        <label class="block text-xs text-gray-700 mb-1">Начало</label>
                        <input 
                            type="date" 
                            v-model="newVacation.start_date"
                            class="w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            required
                        />
                    </div>
                    <div class="col-span-3">
                        <label class="block text-xs text-gray-700 mb-1">Конец</label>
                        <input 
                            type="date" 
                            v-model="newVacation.end_date"
                            class="w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            required
                        />
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-700 mb-1">Тип</label>
                        <select 
                            v-model="newVacation.type"
                            class="w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option value="vacation">Отпуск</option>
                            <option value="sick_leave">Больничный</option>
                            <option value="day_off">Выходной</option>
                        </select>
                    </div>
                    <div class="col-span-3">
                        <label class="block text-xs text-gray-700 mb-1">Причина</label>
                        <input 
                            type="text" 
                            v-model="newVacation.reason"
                            placeholder="Необязательно"
                            class="w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        />
                    </div>
                    <div class="col-span-1 flex items-end">
                        <button
                            type="submit"
                            :disabled="processing"
                            class="w-full px-3 py-2 bg-indigo-600 text-white rounded text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                        >
                            +
                        </button>
                    </div>
                </form>
            </div>

            <!-- Список отпусков -->
            <div>
                <h4 class="text-sm font-medium text-gray-900 mb-3">Запланированные отпуска</h4>
                <div v-if="loading" class="text-center py-8 text-gray-500">
                    <svg class="animate-spin h-8 w-8 mx-auto text-gray-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-2">Загрузка...</p>
                </div>
                
                <div v-else-if="vacations.length === 0" class="text-center py-8 text-gray-500">
                    Нет запланированных отпусков
                </div>

                <div v-else class="space-y-2">
                    <div 
                        v-for="vacation in sortedVacations" 
                        :key="vacation.id"
                        class="flex items-center justify-between p-3 border border-gray-200 rounded-lg hover:bg-gray-50"
                        :class="{
                            'bg-green-50 border-green-200': vacation.type === 'vacation',
                            'bg-amber-50 border-amber-200': vacation.type === 'sick_leave',
                            'bg-blue-50 border-blue-200': vacation.type === 'day_off',
                        }"
                    >
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm">
                                    {{ formatDate(vacation.start_date) }} - {{ formatDate(vacation.end_date) }}
                                </span>
                                <span 
                                    class="px-2 py-0.5 text-xs font-medium rounded"
                                    :class="{
                                        'bg-green-100 text-green-800': vacation.type === 'vacation',
                                        'bg-amber-100 text-amber-800': vacation.type === 'sick_leave',
                                        'bg-blue-100 text-blue-800': vacation.type === 'day_off',
                                    }"
                                >
                                    {{ getTypeLabel(vacation.type) }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    ({{ getDaysCount(vacation.start_date, vacation.end_date) }} дн.)
                                </span>
                            </div>
                            <p v-if="vacation.reason" class="text-sm text-gray-600 mt-1">{{ vacation.reason }}</p>
                        </div>
                        <button
                            @click="deleteVacation(vacation)"
                            class="text-red-600 hover:text-red-800 text-sm font-medium ml-4"
                        >
                            Удалить
                        </button>
                    </div>
                </div>
            </div>

            <!-- Ошибки -->
            <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-3">
                <p class="text-sm text-red-800">{{ error }}</p>
            </div>
        </div>

        <!-- Действия -->
        <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
            <button
                type="button"
                @click="close"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Закрыть
            </button>
        </div>
    </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { format, differenceInDays } from 'date-fns'
import { ru } from 'date-fns/locale'
import Modal from '../Modal.vue'
import axios from 'axios'

const props = defineProps({
    show: Boolean,
    employee: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['close', 'saved'])

const vacations = ref([])
const loading = ref(false)
const processing = ref(false)
const error = ref(null)

const newVacation = ref({
    start_date: '',
    end_date: '',
    type: 'vacation',
    reason: ''
})

const sortedVacations = computed(() => {
    return [...vacations.value].sort((a, b) => {
        return new Date(a.start_date) - new Date(b.start_date)
    })
})

const getTypeLabel = (type) => {
    const labels = {
        vacation: 'Отпуск',
        sick_leave: 'Больничный',
        day_off: 'Выходной'
    }
    return labels[type] || type
}

const formatDate = (date) => {
    return format(new Date(date), 'dd MMM yyyy', { locale: ru })
}

const getDaysCount = (startDate, endDate) => {
    return differenceInDays(new Date(endDate), new Date(startDate)) + 1
}

const fetchVacations = async () => {
    if (!props.employee?.id) return
    
    loading.value = true
    error.value = null
    
    try {
        const response = await axios.get(`/api/vacations`, {
            params: {
                employee_id: props.employee.id
            }
        })
        vacations.value = response.data.data || []
    } catch (err) {
        console.error('Error fetching vacations:', err)
        error.value = 'Не удалось загрузить отпуска'
    } finally {
        loading.value = false
    }
}

const addVacation = async () => {
    if (!props.employee?.id) return
    
    processing.value = true
    error.value = null
    
    try {
        await axios.post('/api/vacations', {
            employee_id: props.employee.id,
            ...newVacation.value
        })
        
        // Очистить форму
        newVacation.value = {
            start_date: '',
            end_date: '',
            type: 'vacation',
            reason: ''
        }
        
        // Обновить список
        await fetchVacations()
        emit('saved')
    } catch (err) {
        console.error('Error adding vacation:', err)
        error.value = err.response?.data?.message || 'Не удалось добавить отпуск'
    } finally {
        processing.value = false
    }
}

const deleteVacation = async (vacation) => {
    if (!confirm(`Удалить отпуск с ${formatDate(vacation.start_date)} по ${formatDate(vacation.end_date)}?`)) {
        return
    }
    
    try {
        await axios.delete(`/api/vacations/${vacation.id}`)
        await fetchVacations()
        emit('saved')
    } catch (err) {
        console.error('Error deleting vacation:', err)
        error.value = 'Не удалось удалить отпуск'
    }
}

const close = () => {
    emit('close')
}

watch(() => props.show, (newVal) => {
    if (newVal && props.employee) {
        fetchVacations()
    }
})
</script>
