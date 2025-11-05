<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Статусы бронирований</h1>
                    <button
                        @click="openCreateModal"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        Добавить статус
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Название</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Цвет</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Тип</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="status in calendarStore.statuses" :key="status.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ status.name }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <div
                                        class="w-8 h-8 rounded border border-gray-300"
                                        :style="{ backgroundColor: status.color }"
                                    ></div>
                                    <span class="text-sm text-gray-600">{{ status.color }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    :class="status.is_system ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800'"
                                    class="px-2 py-1 text-xs font-medium rounded"
                                >
                                    {{ status.is_system ? 'Системный' : 'Пользовательский' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <button @click="editStatus(status)" class="text-blue-600 hover:text-blue-800 mr-3">
                                    Редактировать
                                </button>
                                <button
                                    v-if="!status.is_system"
                                    @click="deleteStatus(status)"
                                    :disabled="status.bookings_count > 0"
                                    :class="status.bookings_count > 0 ? 'text-gray-400 cursor-not-allowed' : 'text-red-600 hover:text-red-800'"
                                >
                                    Удалить
                                </button>
                                <span v-else class="text-gray-400">Системный</span>
                            </td>
                        </tr>
                        <tr v-if="calendarStore.statuses.length === 0">
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                Статусы не найдены
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Status Modal -->
        <StatusModal
            :show="showModal"
            :status="selectedStatus"
            @close="closeModal"
            @saved="handleSaved"
        />
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusModal from '@/Components/Modals/StatusModal.vue';
import { useCalendarStore } from '@/stores/calendar';
import axios from 'axios';

const calendarStore = useCalendarStore();

const showModal = ref(false);
const selectedStatus = ref(null);

onMounted(async () => {
    await calendarStore.fetchReferenceData();
});

const openCreateModal = () => {
    selectedStatus.value = null;
    showModal.value = true;
};

const editStatus = (status) => {
    selectedStatus.value = status;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedStatus.value = null;
};

const handleSaved = async () => {
    await calendarStore.fetchReferenceData();
};

const deleteStatus = async (status) => {
    if (!confirm(`Удалить статус "${status.name}"?`)) return;
    
    try {
        await axios.delete(`/api/statuses/${status.id}`);
        await calendarStore.fetchReferenceData();
    } catch (error) {
        if (error.response?.status === 422) {
            alert('Нельзя удалить статус, используемый в бронированиях');
        } else {
            alert('Ошибка при удалении статуса');
        }
    }
};
</script>
