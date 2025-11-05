<template>
    <Modal :show="show" @close="close" :title="isEdit ? 'Редактировать услугу' : 'Новая услуга'" max-width="lg">
        <form @submit.prevent="submit">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                    <input
                        type="text"
                        v-model="form.name"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    />
                    <p v-if="getError('name')" class="mt-1 text-sm text-red-600">{{ getError('name') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Место работы *</label>
                    <select
                        v-model="form.workplace_id"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option value="">Выберите место работы</option>
                        <option v-for="workplace in workplaces" :key="workplace.id" :value="workplace.id">
                            {{ workplace.name }}
                        </option>
                    </select>
                    <p v-if="getError('workplace_id')" class="mt-1 text-sm text-red-600">{{ getError('workplace_id') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Длительность *</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Часы</label>
                                <input
                                    type="number"
                                    v-model.number="durationHours"
                                    min="0"
                                    max="12"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Минуты</label>
                                <input
                                    type="number"
                                    v-model.number="durationMinutes"
                                    min="0"
                                    max="59"
                                    step="5"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Итого: {{ totalDurationMinutes }} мин ({{ formatDuration(totalDurationMinutes) }})</p>
                        <p v-if="getError('duration_minutes')" class="mt-1 text-sm text-red-600">{{ getError('duration_minutes') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Цена (₽) *</label>
                        <input
                            type="number"
                            v-model.number="form.price"
                            min="0"
                            step="100"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        />
                        <p v-if="getError('price')" class="mt-1 text-sm text-red-600">{{ getError('price') }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
                </div>

                <div class="flex items-center">
                    <input
                        type="checkbox"
                        v-model="form.is_active"
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label class="ml-2 text-sm text-gray-700">Активна</label>
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
import Modal from '../Modal.vue'
import { useCalendarStore } from '@/stores/calendar'
import { useToast } from '@/composables/useToast'
import axios from 'axios'

const props = defineProps({
    show: Boolean,
    service: Object,
})

const emit = defineEmits(['close', 'saved'])

const calendarStore = useCalendarStore()
const toast = useToast()

const form = ref({
    name: '',
    workplace_id: '',
    duration: 60,
    price: 0,
    description: '',
    is_active: true,
})

const errors = ref({})
const processing = ref(false)

// Отдельные поля для часов и минут
const durationHours = ref(0)
const durationMinutes = ref(60)

// Вычисляем общую длительность в минутах
const totalDurationMinutes = computed(() => {
    return (durationHours.value || 0) * 60 + (durationMinutes.value || 0)
})

// Форматирование длительности для отображения
const formatDuration = (minutes) => {
    const hours = Math.floor(minutes / 60)
    const mins = minutes % 60
    if (hours === 0) return `${mins} мин`
    if (mins === 0) return `${hours} ч`
    return `${hours} ч ${mins} мин`
}

const getError = (field) => {
    const error = errors.value[field]
    if (!error) return null
    return Array.isArray(error) ? error[0] : error
}

const isEdit = computed(() => !!props.service?.id)
const workplaces = computed(() => calendarStore.workplaces || [])

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.service) {
            const duration = props.service.duration || props.service.duration_minutes || 60
            form.value = {
                name: props.service.name,
                workplace_id: props.service.workplace_id || '',
                duration: duration,
                price: props.service.price,
                description: props.service.description || '',
                is_active: props.service.is_active,
            }
            // Конвертируем минуты в часы и минуты
            durationHours.value = Math.floor(duration / 60)
            durationMinutes.value = duration % 60
        } else {
            resetForm()
        }
        errors.value = {}
    }
})

const resetForm = () => {
    form.value = {
        name: '',
        workplace_id: '',
        duration: 60,
        price: 0,
        description: '',
        is_active: true,
    }
    durationHours.value = 1
    durationMinutes.value = 0
}

const submit = async () => {
    if (processing.value) return
    
    processing.value = true
    errors.value = {}

    try {
        // Обновляем длительность перед отправкой
        const dataToSend = {
            ...form.value,
            duration_minutes: totalDurationMinutes.value
        }
        
        // Удаляем старое поле duration если есть
        delete dataToSend.duration
        
        if (isEdit.value) {
            await axios.put(`/api/services/${props.service.id}`, dataToSend)
            toast.success('Услуга обновлена', 'Изменения успешно сохранены')
        } else {
            await axios.post('/api/services', dataToSend)
            toast.success('Услуга создана', 'Новая услуга успешно добавлена')
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
                error.response?.data?.message || 'Не удалось сохранить услугу'
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
