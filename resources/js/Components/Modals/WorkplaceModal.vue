<template>
    <Modal :show="show" @close="close" :title="isEdit ? 'Редактировать место работы' : 'Новое место работы'" max-width="lg">
        <form @submit.prevent="submit">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Название *</label>
                    <input
                        type="text"
                        v-model="form.name"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Кабинет №1, Студия красоты"
                        required
                    />
                    <p v-if="getError('name')" class="mt-1 text-sm text-red-600">{{ getError('name') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Описание</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Дополнительная информация о месте..."
                    ></textarea>
                </div>

                <div class="flex items-center">
                    <input
                        type="checkbox"
                        v-model="form.is_active"
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label class="ml-2 text-sm text-gray-700">Активно</label>
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
    workplace: Object,
})

const emit = defineEmits(['close', 'saved'])

const toast = useToast()

const form = ref({
    name: '',
    description: '',
    is_active: true,
})

const errors = ref({})
const processing = ref(false)

const getError = (field) => {
    const error = errors.value[field]
    if (!error) return null
    return Array.isArray(error) ? error[0] : error
}

const isEdit = computed(() => !!props.workplace?.id)

watch(() => props.show, (newVal) => {
    if (newVal) {
        if (props.workplace) {
            form.value = {
                name: props.workplace.name,
                description: props.workplace.description || '',
                is_active: props.workplace.is_active,
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
        description: '',
        is_active: true,
    }
}

const submit = async () => {
    if (processing.value) return
    
    processing.value = true
    errors.value = {}

    try {
        if (isEdit.value) {
            await axios.put(`/api/workplaces/${props.workplace.id}`, form.value)
            toast.success('Место работы обновлено', 'Изменения успешно сохранены')
        } else {
            await axios.post('/api/workplaces', form.value)
            toast.success('Место работы создано', 'Новое место работы успешно добавлено')
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
                error.response?.data?.message || 'Не удалось сохранить место работы'
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
