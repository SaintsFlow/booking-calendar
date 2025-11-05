<template>
    <Modal :show="show" @close="close" :title="isEdit ? 'Редактировать статус' : 'Новый статус'" max-width="md">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Slug (для системы) *</label>
                    <input
                        type="text"
                        v-model="form.slug"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="confirmed, cancelled, etc."
                        :disabled="isEdit"
                        required
                    />
                    <p v-if="getError('slug')" class="mt-1 text-sm text-red-600">{{ getError('slug') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Цвет *</label>
                    <div class="flex items-center space-x-3">
                        <input
                            type="color"
                            v-model="form.color"
                            class="h-10 w-20 rounded border-gray-300 cursor-pointer"
                        />
                        <input
                            type="text"
                            v-model="form.color"
                            class="flex-1 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="#22c55e"
                        />
                        <div
                            class="h-10 w-10 rounded-lg border-2 border-gray-300"
                            :style="{ backgroundColor: form.color }"
                        ></div>
                    </div>
                    <p v-if="getError('color')" class="mt-1 text-sm text-red-600">{{ getError('color') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Предустановленные цвета</label>
                    <div class="grid grid-cols-8 gap-2">
                        <button
                            v-for="presetColor in presetColors"
                            :key="presetColor"
                            type="button"
                            @click="form.color = presetColor"
                            class="h-8 w-8 rounded border-2 hover:scale-110 transition"
                            :class="form.color === presetColor ? 'border-indigo-500 ring-2 ring-indigo-300' : 'border-gray-300'"
                            :style="{ backgroundColor: presetColor }"
                        ></button>
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
import Modal from '../Modal.vue'
import { useToast } from '@/composables/useToast'
import axios from 'axios'

const props = defineProps({
    show: Boolean,
    status: Object,
})

const emit = defineEmits(['close', 'saved'])

const toast = useToast()

const form = ref({
    name: '',
    slug: '',
    color: '#22c55e',
})

const errors = ref({})
const processing = ref(false)

const getError = (field) => {
    const error = errors.value[field]
    if (!error) return null
    return Array.isArray(error) ? error[0] : error
}

const presetColors = [
    '#22c55e', // green
    '#3b82f6', // blue
    '#f59e0b', // amber
    '#ef4444', // red
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#6b7280', // gray
    '#14b8a6', // teal
]

const isEdit = computed(() => !!props.status?.id)

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.status) {
            form.value = {
                name: props.status.name,
                slug: props.status.slug,
                color: props.status.color,
            }
        } else {
            resetForm()
        }
        errors.value = {}
    }
})

const resetForm = () => {
    form.value = {
        name: '',
        slug: '',
        color: '#22c55e',
    }
}

const submit = async () => {
    if (processing.value) return
    
    processing.value = true
    errors.value = {}

    try {
        if (isEdit.value) {
            await axios.put(`/api/statuses/${props.status.id}`, form.value)
            toast.success('Статус обновлён', 'Изменения успешно сохранены')
        } else {
            await axios.post('/api/statuses', form.value)
            toast.success('Статус создан', 'Новый статус успешно добавлен')
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
                error.response?.data?.message || 'Не удалось сохранить статус'
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
