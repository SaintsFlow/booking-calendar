<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Управление кабинетами</h1>
                        <p class="text-gray-600 mt-1">Супер-администратор: управление клиентскими кабинетами</p>
                    </div>
                    <button
                        @click="showCreateModal = true"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    >
                        + Создать кабинет
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Название</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Домен</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус подписки</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Пробный период</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="tenant in tenants" :key="tenant.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ tenant.name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ tenant.domain || '-' }}</td>
                            <td class="px-6 py-4">
                                <span
                                    :class="{
                                        'bg-green-100 text-green-800': tenant.subscription_status === 'active',
                                        'bg-yellow-100 text-yellow-800': tenant.subscription_status === 'trial',
                                        'bg-red-100 text-red-800': tenant.subscription_status === 'blocked',
                                    }"
                                    class="px-2 py-1 text-xs font-medium rounded"
                                >
                                    {{ getStatusLabel(tenant.subscription_status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ tenant.trial_ends_at ? formatDate(tenant.trial_ends_at) : '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm space-x-3">
                                <button @click="impersonate(tenant)" class="text-blue-600 hover:text-blue-800">
                                    Войти как
                                </button>
                                <button @click="editTenant(tenant)" class="text-green-600 hover:text-green-800">
                                    Редактировать
                                </button>
                                <button
                                    @click="toggleBlock(tenant)"
                                    :class="tenant.subscription_status === 'blocked' ? 'text-green-600 hover:text-green-800' : 'text-red-600 hover:text-red-800'"
                                >
                                    {{ tenant.subscription_status === 'blocked' ? 'Разблокировать' : 'Заблокировать' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="tenants.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                Кабинеты не найдены
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Modal создания/редактирования кабинета -->
            <div
                v-if="showCreateModal || showEditModal"
                class="fixed inset-0 flex items-center justify-center z-50"
                style="background-color: rgba(0, 0, 0, 0.5);"
                @click.self="closeModal"
            >
                <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md" @click.stop>
                    <h2 class="text-xl font-bold text-gray-900 mb-4">
                        {{ showEditModal ? 'Редактировать кабинет' : 'Создать новый кабинет' }}
                    </h2>
                    
                    <form @submit.prevent="showEditModal ? updateTenant() : createTenant()" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Название кабинета *
                            </label>
                            <input
                                v-model="newTenant.name"
                                type="text"
                                required
                                class="w-full border-gray-300 rounded-lg"
                                placeholder="Например: Салон красоты 'Венера'"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Домен (необязательно)
                            </label>
                            <input
                                v-model="newTenant.domain"
                                type="text"
                                class="w-full border-gray-300 rounded-lg"
                                placeholder="salon.example.com"
                            />
                        </div>

                        <hr class="my-4" />

                        <h3 class="text-lg font-semibold text-gray-900">Администратор кабинета</h3>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Имя администратора *
                            </label>
                            <input
                                v-model="newTenant.admin_name"
                                type="text"
                                required
                                class="w-full border-gray-300 rounded-lg"
                                placeholder="Иван Иванов"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Email администратора *
                            </label>
                            <input
                                v-model="newTenant.admin_email"
                                type="email"
                                :required="!showEditModal"
                                :disabled="showEditModal"
                                class="w-full border-gray-300 rounded-lg disabled:bg-gray-100"
                                placeholder="admin@example.com"
                            />
                            <p v-if="showEditModal" class="text-xs text-gray-500 mt-1">
                                Email администратора нельзя изменить
                            </p>
                        </div>

                        <div v-if="!showEditModal">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Пароль администратора *
                            </label>
                            <input
                                v-model="newTenant.admin_password"
                                type="password"
                                required
                                minlength="8"
                                class="w-full border-gray-300 rounded-lg"
                                placeholder="Минимум 8 символов"
                            />
                        </div>

                        <div class="flex gap-3 pt-4">
                            <button
                                type="submit"
                                :disabled="creating"
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ creating ? (showEditModal ? 'Сохранение...' : 'Создание...') : (showEditModal ? 'Сохранить' : 'Создать кабинет') }}
                            </button>
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                            >
                                Отмена
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { ru } from 'date-fns/locale';
import AppLayout from '@/Layouts/AppLayout.vue';

const tenants = ref([]);
const showCreateModal = ref(false);
const showEditModal = ref(false);
const creating = ref(false);
const editingTenant = ref(null);
const newTenant = ref({
    name: '',
    domain: '',
    admin_name: '',
    admin_email: '',
    admin_password: '',
});

onMounted(async () => {
    try {
        const response = await window.axios.get('/api/admin/tenants');
        tenants.value = response.data.data || response.data;
    } catch (error) {
        console.error('Failed to load tenants:', error);
    }
});

const getStatusLabel = (status) => {
    const labels = {
        trial: 'Пробный',
        active: 'Активна',
        blocked: 'Заблокирован',
    };
    return labels[status] || status;
};

const formatDate = (date) => {
    return format(new Date(date), 'd MMMM yyyy', { locale: ru });
};

const createTenant = async () => {
    creating.value = true;
    try {
        const response = await window.axios.post('/api/admin/tenants', newTenant.value);
        
        // Добавляем новый тенант в список
        tenants.value.push(response.data.tenant || response.data);
        
        // Закрываем модалку и очищаем форму
        closeModal();
        
        alert('Кабинет успешно создан!');
    } catch (error) {
        console.error('Failed to create tenant:', error);
        
        // Проверка на CSRF ошибку
        if (error.response?.status === 419) {
            if (confirm('Сессия истекла. Перезагрузить страницу?')) {
                window.location.reload();
            }
            return;
        }
        
        const message = error.response?.data?.message || 'Ошибка при создании кабинета';
        alert(message);
    } finally {
        creating.value = false;
    }
};

const closeModal = () => {
    showCreateModal.value = false;
    showEditModal.value = false;
    editingTenant.value = null;
    newTenant.value = {
        name: '',
        domain: '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
    };
};

const editTenant = (tenant) => {
    editingTenant.value = tenant;
    newTenant.value = {
        name: tenant.name,
        domain: tenant.domain || '',
        admin_name: tenant.admin_name || '',
        admin_email: tenant.admin_email || '',
        admin_password: '',
    };
    showEditModal.value = true;
};

const updateTenant = async () => {
    creating.value = true;
    try {
        const response = await window.axios.put(`/api/admin/tenants/${editingTenant.value.id}`, {
            name: newTenant.value.name,
            domain: newTenant.value.domain || null,
        });
        
        // Обновляем тенант в списке
        const index = tenants.value.findIndex(t => t.id === editingTenant.value.id);
        if (index !== -1) {
            tenants.value[index] = { ...tenants.value[index], ...response.data };
        }
        
        closeModal();
        alert('Кабинет успешно обновлён!');
    } catch (error) {
        console.error('Failed to update tenant:', error);
        
        // Проверка на CSRF ошибку
        if (error.response?.status === 419) {
            if (confirm('Сессия истекла. Перезагрузить страницу?')) {
                window.location.reload();
            }
            return;
        }
        
        const message = error.response?.data?.message || 'Ошибка при обновлении кабинета';
        alert(message);
    } finally {
        creating.value = false;
    }
};

const impersonate = async (tenant) => {
    if (!confirm(`Войти в систему как тенант "${tenant.name}"?`)) return;
    
    try {
        await window.axios.post(`/api/admin/tenants/${tenant.id}/impersonate`);
        // После успешной имперсонации перезагружаем страницу
        router.reload();
    } catch (error) {
        alert('Ошибка при входе в систему тенанта');
    }
};

const toggleBlock = async (tenant) => {
    const action = tenant.subscription_status === 'blocked' ? 'разблокировать' : 'заблокировать';
    if (!confirm(`Вы уверены, что хотите ${action} тенант "${tenant.name}"?`)) return;
    
    const newStatus = tenant.subscription_status === 'blocked' ? 'active' : 'blocked';
    
    try {
        await window.axios.patch(`/api/admin/tenants/${tenant.id}/subscription`, {
            subscription_status: newStatus,
        });
        
        // Обновляем локальные данные
        tenant.subscription_status = newStatus;
    } catch (error) {
        alert('Ошибка при изменении статуса тенанта');
    }
};
</script>
