<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    subscriptions: Object,
});

const confirmAction = ref(null);
const showConfirmModal = ref(false);

const openConfirmModal = (action, subscription) => {
    confirmAction.value = { action, subscription };
    showConfirmModal.value = true;
};

const closeConfirmModal = () => {
    confirmAction.value = null;
    showConfirmModal.value = false;
};

const executeAction = () => {
    if (!confirmAction.value) return;

    const { action, subscription } = confirmAction.value;
    
    router.post(`/admin/subscriptions/${subscription.id}/${action}`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            closeConfirmModal();
        },
    });
};

const getStatusColor = (status) => {
    const colors = {
        active: 'bg-green-100 text-green-800',
        paused: 'bg-yellow-100 text-yellow-800',
        cancelled: 'bg-red-100 text-red-800',
        expired: 'bg-gray-100 text-gray-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const getStatusText = (status) => {
    const texts = {
        active: 'Активна',
        paused: 'Приостановлена',
        cancelled: 'Отменена',
        expired: 'Истекла',
    };
    return texts[status] || status;
};

const deleteSubscription = (subscription) => {
    if (confirm('Вы уверены, что хотите удалить эту подписку?')) {
        router.delete(`/admin/subscriptions/${subscription.id}`, {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Управление подписками" />

    <AppLayout>
        <div class="max-w-7xl mx-auto">
            <!-- Заголовок -->
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Управление подписками</h1>
                    <p class="mt-2 text-sm text-gray-600">
                        Просмотр и управление подписками тенантов
                    </p>
                </div>
                <Link
                    :href="route('admin.subscriptions.create')"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium"
                >
                    Создать подписку
                </Link>
            </div>

            <!-- Таблица подписок -->
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Тенант
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                План
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Статус
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Начало
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Окончание
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Действия
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="subscription in subscriptions.data" :key="subscription.id">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ subscription.tenant.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ subscription.plan_name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                    :class="getStatusColor(subscription.status)"
                                >
                                    {{ getStatusText(subscription.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ new Date(subscription.starts_at).toLocaleDateString('ru-RU') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString('ru-RU') : '∞' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                <Link
                                    :href="route('admin.subscriptions.show', subscription.id)"
                                    class="text-blue-600 hover:text-blue-900"
                                >
                                    Просмотр
                                </Link>
                                
                                <Link
                                    :href="route('admin.subscriptions.edit', subscription.id)"
                                    class="text-indigo-600 hover:text-indigo-900"
                                >
                                    Изменить
                                </Link>

                                <button
                                    v-if="subscription.status === 'active'"
                                    @click="openConfirmModal('pause', subscription)"
                                    class="text-yellow-600 hover:text-yellow-900"
                                >
                                    Приостановить
                                </button>

                                <button
                                    v-if="subscription.status === 'paused'"
                                    @click="openConfirmModal('resume', subscription)"
                                    class="text-green-600 hover:text-green-900"
                                >
                                    Возобновить
                                </button>

                                <button
                                    v-if="subscription.status === 'active' || subscription.status === 'paused'"
                                    @click="openConfirmModal('cancel', subscription)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    Отменить
                                </button>

                                <button
                                    @click="deleteSubscription(subscription)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    Удалить
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Пагинация -->
                <div v-if="subscriptions.links.length > 3" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-700">
                            Показано {{ subscriptions.from }} - {{ subscriptions.to }} из {{ subscriptions.total }}
                        </div>
                        <div class="flex space-x-2">
                            <Link
                                v-for="(link, index) in subscriptions.links"
                                :key="index"
                                :href="link.url"
                                v-html="link.label"
                                :class="[
                                    'px-3 py-2 text-sm rounded-md',
                                    link.active
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300',
                                    !link.url && 'opacity-50 cursor-not-allowed'
                                ]"
                                :disabled="!link.url"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Модальное окно подтверждения -->
        <div
            v-if="showConfirmModal"
            class="fixed z-10 inset-0 overflow-y-auto"
            @click.self="closeConfirmModal"
        >
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">
                                    Подтверждение действия
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Вы уверены, что хотите {{ confirmAction?.action === 'pause' ? 'приостановить' : confirmAction?.action === 'resume' ? 'возобновить' : 'отменить' }} подписку тенанта "{{ confirmAction?.subscription?.tenant?.name }}"?
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button
                            @click="executeAction"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Подтвердить
                        </button>
                        <button
                            @click="closeConfirmModal"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Отмена
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
