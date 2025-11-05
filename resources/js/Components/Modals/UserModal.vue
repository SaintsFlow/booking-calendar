<template>
    <Modal :show="show" @close="close" :title="isEdit ? 'Редактировать сотрудника' : 'Новый сотрудник'" max-width="2xl">
        <form @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <!-- Левая колонка -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Имя *</label>
                        <input
                            type="text"
                            v-model="form.name"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        />
                        <p v-if="getError('name')" class="mt-1 text-sm text-red-600">{{ getError('name') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input
                            type="email"
                            v-model="form.email"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :disabled="isEdit"
                            required
                        />
                        <p v-if="getError('email')" class="mt-1 text-sm text-red-600">{{ getError('email') }}</p>
                    </div>

                    <div v-if="!isEdit">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Пароль *</label>
                        <input
                            type="password"
                            v-model="form.password"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            :required="!isEdit"
                        />
                        <p v-if="getError('password')" class="mt-1 text-sm text-red-600">{{ getError('password') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Телефон</label>
                        <input
                            ref="phoneInput"
                            type="tel"
                            v-model="form.phone"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="+7 (___) ___-__-__"
                        />
                        <p v-if="getError('phone')" class="mt-1 text-sm text-red-600">{{ getError('phone') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bitrix24 User ID</label>
                        <input
                            type="text"
                            v-model="form.bitrix24_user_id"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="ID пользователя в Bitrix24"
                        />
                        <p v-if="getError('bitrix24_user_id')" class="mt-1 text-sm text-red-600">{{ getError('bitrix24_user_id') }}</p>
                        <p class="mt-1 text-xs text-gray-500">Необязательное поле для интеграции с Bitrix24</p>
                    </div>
                </div>

                <!-- Правая колонка -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Роль *</label>
                        <select
                            v-model="form.role"
                            class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        >
                            <option value="employee">Сотрудник</option>
                            <option value="manager">Менеджер</option>
                            <option value="admin">Администратор</option>
                        </select>
                        <p v-if="getError('role')" class="mt-1 text-sm text-red-600">{{ getError('role') }}</p>
                    </div>

                    <div v-if="form.role === 'employee'">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Места работы</label>
                        <div class="space-y-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-3">
                            <label v-for="workplace in workplaces" :key="workplace.id" class="flex items-center space-x-2">
                                <input
                                    type="checkbox"
                                    :value="workplace.id"
                                    v-model="form.workplace_ids"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-sm text-gray-700">{{ workplace.name }}</span>
                            </label>
                        </div>
                        <p v-if="getError('workplace_ids')" class="mt-1 text-sm text-red-600">{{ getError('workplace_ids') }}</p>
                    </div>

                    <div>
                        <label class="flex items-center space-x-2">
                            <input
                                type="checkbox"
                                v-model="form.is_active"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-sm font-medium text-gray-700">Активен</span>
                        </label>
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
import { ref, computed, watch, nextTick } from 'vue'
import Modal from '../Modal.vue'
import { useCalendarStore } from '@/stores/calendar'
import { useToast } from '@/composables/useToast'
import axios from 'axios'

const props = defineProps({
    show: Boolean,
    user: Object,
})

const emit = defineEmits(['close', 'saved'])

const calendarStore = useCalendarStore()
const toast = useToast()
const phoneInput = ref(null)
let phoneMask = null

const form = ref({
    name: '',
    email: '',
    password: '',
    phone: '',
    role: 'employee',
    workplace_ids: [],
    is_active: true,
    bitrix24_user_id: '',
})

const errors = ref({})
const processing = ref(false)

const getError = (field) => {
    const error = errors.value[field]
    if (!error) return null
    return Array.isArray(error) ? error[0] : error
}

const isEdit = computed(() => !!props.user?.id)
const workplaces = computed(() => calendarStore.workplaces || [])

watch(() => props.show, async (newVal) => {
    if (newVal) {
        if (props.user) {
            form.value = {
                name: props.user.name,
                email: props.user.email,
                password: '',
                phone: props.user.phone || '',
                role: props.user.role,
                workplace_ids: props.user.workplaces?.map(w => w.id) || [],
                is_active: props.user.is_active !== false,
                bitrix24_user_id: props.user.bitrix24_user_id || '',
            }
        } else {
            resetForm()
        }
        errors.value = {}
        
        await nextTick()
        initPhoneMask()
    } else {
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
        
        phoneMask.on('accept', () => {
            form.value.phone = phoneMask.value
        })
        
        if (form.value.phone) {
            phoneMask.value = form.value.phone
        }
    }
}

const resetForm = () => {
    form.value = {
        name: '',
        email: '',
        password: '',
        phone: '',
        role: 'employee',
        workplace_ids: [],
        is_active: true,
        bitrix24_user_id: '',
    }
}

const submit = async () => {
    if (processing.value) return
    
    processing.value = true
    errors.value = {}

    try {
        const data = { ...form.value }
        
        // Удаляем пароль если редактируем и он пустой
        if (isEdit.value && !data.password) {
            delete data.password
        }
        
        // Удаляем workplace_ids если не сотрудник
        if (data.role !== 'employee') {
            delete data.workplace_ids
        }

        if (isEdit.value) {
            await axios.put(`/api/users/${props.user.id}`, data)
            toast.success('Сотрудник обновлён', 'Изменения успешно сохранены')
        } else {
            await axios.post('/api/users', data)
            toast.success('Сотрудник создан', 'Новый сотрудник успешно добавлен')
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
                error.response?.data?.message || 'Не удалось сохранить сотрудника'
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
