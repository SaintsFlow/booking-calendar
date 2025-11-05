<template>
    <Modal :show="show" @close="close" title="График работы" max-width="3xl">
        <form @submit.prevent="submit">
            <div class="space-y-6">
                <!-- Заголовок -->
                <div>
                    <h3 class="text-lg font-medium text-gray-900">{{ title }}</h3>
                    <p class="mt-1 text-sm text-gray-500">Установите рабочие часы для каждого дня недели</p>
                </div>

                <!-- Дни недели -->
                <div class="space-y-3">
                    <div 
                        v-for="day in daysOfWeek" 
                        :key="day.key"
                        class="flex items-center gap-4 p-3 border border-gray-200 rounded-lg hover:bg-gray-50"
                    >
                        <!-- Чекбокс "работает" -->
                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                :id="`working-${day.key}`"
                                v-model="schedule[day.key].is_working"
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                        </div>

                        <!-- День недели -->
                        <label :for="`working-${day.key}`" class="min-w-[120px] text-sm font-medium text-gray-700 cursor-pointer">
                            {{ day.label }}
                        </label>

                        <!-- Время начала и конца -->
                        <div v-if="schedule[day.key].is_working" class="flex items-center gap-3 flex-1">
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">Начало</label>
                                <input 
                                    type="time"
                                    v-model="schedule[day.key].start"
                                    class="w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                            <span class="text-gray-400 mt-5">-</span>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">Конец</label>
                                <input 
                                    type="time"
                                    v-model="schedule[day.key].end"
                                    class="w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                        </div>
                        <div v-else class="flex-1 text-sm text-gray-400 italic">
                            Выходной день
                        </div>
                    </div>
                </div>

                <!-- Быстрые действия -->
                <div class="flex gap-2 pt-4 border-t">
                    <button
                        type="button"
                        @click="applyWorkweek"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        Применить 5/2 (пн-пт 9:00-18:00)
                    </button>
                    <button
                        type="button"
                        @click="applyEveryDay"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        Каждый день (9:00-18:00)
                    </button>
                    <button
                        type="button"
                        @click="clearAll"
                        class="text-sm text-red-600 hover:text-red-800 font-medium"
                    >
                        Очистить всё
                    </button>
                </div>

                <!-- Ошибки -->
                <div v-if="errors && Object.keys(errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-3">
                    <div class="text-sm text-red-800">
                        <p class="font-medium mb-1">Ошибки:</p>
                        <ul class="list-disc list-inside">
                            <li v-for="(error, field) in errors" :key="field">
                                {{ Array.isArray(error) ? error[0] : error }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Действия -->
            <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                <button
                    type="button"
                    @click="close"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Отмена
                </button>
                <button
                    type="submit"
                    :disabled="processing"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                >
                    <span v-if="processing">Сохранение...</span>
                    <span v-else>Сохранить</span>
                </button>
            </div>
        </form>
    </Modal>
</template>

<script setup>
import { ref, watch } from 'vue'
import Modal from '../Modal.vue'

const props = defineProps({
    show: Boolean,
    title: {
        type: String,
        default: 'График работы'
    },
    initialSchedule: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['close', 'save'])

const daysOfWeek = [
    { key: 'monday', label: 'Понедельник' },
    { key: 'tuesday', label: 'Вторник' },
    { key: 'wednesday', label: 'Среда' },
    { key: 'thursday', label: 'Четверг' },
    { key: 'friday', label: 'Пятница' },
    { key: 'saturday', label: 'Суббота' },
    { key: 'sunday', label: 'Воскресенье' },
]

const schedule = ref({})
const processing = ref(false)
const errors = ref({})

// Инициализация графика
const initSchedule = () => {
    const defaultSchedule = {}
    
    daysOfWeek.forEach(day => {
        if (props.initialSchedule && props.initialSchedule[day.key]) {
            defaultSchedule[day.key] = { ...props.initialSchedule[day.key] }
        } else {
            defaultSchedule[day.key] = {
                is_working: false,
                start: '09:00',
                end: '18:00'
            }
        }
    })
    
    schedule.value = defaultSchedule
}

// Применить рабочую неделю (пн-пт)
const applyWorkweek = () => {
    daysOfWeek.forEach(day => {
        if (['saturday', 'sunday'].includes(day.key)) {
            schedule.value[day.key].is_working = false
        } else {
            schedule.value[day.key].is_working = true
            schedule.value[day.key].start = '09:00'
            schedule.value[day.key].end = '18:00'
        }
    })
}

// Применить каждый день
const applyEveryDay = () => {
    daysOfWeek.forEach(day => {
        schedule.value[day.key].is_working = true
        schedule.value[day.key].start = '09:00'
        schedule.value[day.key].end = '18:00'
    })
}

// Очистить всё
const clearAll = () => {
    daysOfWeek.forEach(day => {
        schedule.value[day.key].is_working = false
    })
}

// Отправка формы
const submit = () => {
    emit('save', schedule.value)
}

// Закрыть модальное окно
const close = () => {
    emit('close')
}

// Следим за открытием модального окна
watch(() => props.show, (newVal) => {
    if (newVal) {
        initSchedule()
        errors.value = {}
    }
})
</script>
