<template>
    <Modal :show="show" @close="close" :title="isEdit ? 'Редактировать клиента' : 'Новый клиент'" max-width="lg">
        <form @submit.prevent="submit">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Имя *</label>
                    <input
                        type="text"
                        v-model="form.first_name"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    />
                    <p v-if="getError('first_name')" class="mt-1 text-sm text-red-600">{{ getError('first_name') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Фамилия *</label>
                    <input
                        type="text"
                        v-model="form.last_name"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    />
                    <p v-if="getError('last_name')" class="mt-1 text-sm text-red-600">{{ getError('last_name') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Телефон *</label>
                    <input
                        ref="phoneInput"
                        type="tel"
                        v-model="form.phone"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="+7 (___) ___-__-__"
                        required
                    />
                    <p v-if="getError('phone')" class="mt-1 text-sm text-red-600">{{ getError('phone') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input
                        type="email"
                        v-model="form.email"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <p v-if="getError('email')" class="mt-1 text-sm text-red-600">{{ getError('email') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Примечания</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>
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
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { IMaskDirective } from 'vue-imask'
import Modal from '../Modal.vue'
import axios from 'axios'
import { useToast } from '@/composables/useToast'

const props = defineProps({
    show: Boolean,
    client: Object,
})

const emit = defineEmits(['close', 'saved'])

const toast = useToast()
const phoneInput = ref(null)
let phoneMask = null

const form = ref({
    first_name: '',
    last_name: '',
    phone: '',
    email: '',
    notes: '',
})

const errors = ref({})
const processing = ref(false)

const getError = (field) => {
    const error = errors.value[field]
    if (!error) return null
    return Array.isArray(error) ? error[0] : error
}

const isEdit = computed(() => !!props.client?.id)

// Инициализация маски при показе модалки
watch(() => props.show, async (newVal) => {
    if (newVal) {
        if (props.client) {
            form.value = {
                first_name: props.client.first_name,
                last_name: props.client.last_name,
                phone: props.client.phone,
                email: props.client.email || '',
                notes: props.client.notes || '',
            }
        } else {
            resetForm()
        }
        errors.value = {}
        
        // Применяем маску после рендеринга
        await nextTick()
        initPhoneMask()
    } else {
        // Удаляем маску при закрытии
        if (phoneMask) {
            phoneMask.destroy()
            phoneMask = null
        }
    }
})

const initPhoneMask = async () => {
    if (phoneInput.value) {
        const { default: IMask } = await import('imask')
        
        phoneMask = IMask(phoneInput.value, {
            mask: '+{7} (000) 000-00-00',
            lazy: false,
            placeholderChar: '_'
        })
        
        // Синхронизация значения с формой
        phoneMask.on('accept', () => {
            form.value.phone = phoneMask.value
        })
        
        // Установка начального значения
        if (form.value.phone) {
            phoneMask.value = form.value.phone
        }
    }
}

const resetForm = () => {
    form.value = {
        first_name: '',
        last_name: '',
        phone: '',
        email: '',
        notes: '',
    }
}

const submit = async () => {
    if (processing.value) return
    
    processing.value = true
    errors.value = {}

    try {
        if (isEdit.value) {
            await axios.put(`/api/clients/${props.client.id}`, form.value)
            toast.success('Клиент обновлён', 'Изменения успешно сохранены')
        } else {
            await axios.post('/api/clients', form.value)
            toast.success('Клиент создан', 'Новый клиент успешно добавлен')
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
                error.response?.data?.message || 'Не удалось сохранить клиента'
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
