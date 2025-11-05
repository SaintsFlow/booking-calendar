<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Услуги</h1>
                    <button
                        v-if="$page.props.auth.user?.has_admin_access"
                        @click="openCreateModal"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        Добавить услугу
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Название</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Место работы</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Длительность</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Цена</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Статус</th>
                            <th v-if="$page.props.auth.user?.has_admin_access" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="service in calendarStore.services" :key="service.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ service.name }}</div>
                                <div v-if="service.description" class="text-sm text-gray-500 mt-1">{{ service.description }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ service.workplace?.name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ formatDuration(service.duration) }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ service.price }} ₽</td>
                            <td class="px-6 py-4">
                                <span
                                    :class="service.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                    class="px-2 py-1 text-xs font-medium rounded"
                                >
                                    {{ service.is_active ? 'Активна' : 'Неактивна' }}
                                </span>
                            </td>
                            <td v-if="$page.props.auth.user?.has_admin_access" class="px-6 py-4 text-sm">
                                <button @click="editService(service)" class="text-blue-600 hover:text-blue-800 mr-3">
                                    Редактировать
                                </button>
                                <button @click="deleteService(service)" class="text-red-600 hover:text-red-800">
                                    Удалить
                                </button>
                            </td>
                        </tr>
                        <tr v-if="calendarStore.services.length === 0">
                            <td :colspan="$page.props.auth.user?.has_admin_access ? 6 : 5" class="px-6 py-8 text-center text-gray-500">
                                Услуги не найдены
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Service Modal -->
        <ServiceModal
            :show="showModal"
            :service="selectedService"
            @close="closeModal"
            @saved="handleSaved"
        />
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ServiceModal from '@/Components/Modals/ServiceModal.vue';
import { useCalendarStore } from '@/stores/calendar';
import axios from 'axios';

const calendarStore = useCalendarStore();

const showModal = ref(false);
const selectedService = ref(null);

// Форматирование длительности
const formatDuration = (minutes) => {
    if (!minutes) return '0 мин'
    const hours = Math.floor(minutes / 60)
    const mins = minutes % 60
    if (hours === 0) return `${mins} мин`
    if (mins === 0) return `${hours} ч`
    return `${hours} ч ${mins} мин`
}

onMounted(async () => {
    await calendarStore.fetchReferenceData();
});

const openCreateModal = () => {
    selectedService.value = null;
    showModal.value = true;
};

const editService = (service) => {
    selectedService.value = service;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedService.value = null;
};

const handleSaved = async () => {
    await calendarStore.fetchReferenceData();
};

const deleteService = async (service) => {
    if (!confirm(`Удалить услугу "${service.name}"?`)) return;
    
    try {
        await axios.delete(`/api/services/${service.id}`);
        await calendarStore.fetchReferenceData();
    } catch (error) {
        alert('Ошибка при удалении услуги');
    }
};
</script>
