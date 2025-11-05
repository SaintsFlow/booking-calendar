<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Клиенты</h1>
                    <button
                        @click="openCreateModal"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        Добавить клиента
                    </button>
                </div>

                <!-- Search -->
                <div class="mt-4">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Поиск по имени, телефону или email..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg"
                    />
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Имя</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Телефон</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="client in filteredClients" :key="client.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                {{ client.full_name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ client.phone }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ client.email || '-' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button @click="editClient(client)" class="text-blue-600 hover:text-blue-800 mr-3">
                                    Редактировать
                                </button>
                                <button @click="deleteClient(client)" class="text-red-600 hover:text-red-800">
                                    Удалить
                                </button>
                            </td>
                        </tr>
                        <tr v-if="filteredClients.length === 0">
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                Клиенты не найдены
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Client Modal -->
        <ClientModal
            :show="showModal"
            :client="selectedClient"
            @close="closeModal"
            @saved="handleSaved"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ClientModal from '@/Components/Modals/ClientModal.vue';
import { useCalendarStore } from '@/stores/calendar';
import axios from 'axios';

const calendarStore = useCalendarStore();
const search = ref('');
const showModal = ref(false);
const selectedClient = ref(null);

const filteredClients = computed(() => {
    if (!search.value) return calendarStore.clients;
    
    const searchLower = search.value.toLowerCase();
    return calendarStore.clients.filter(client => {
        const fullName = client.full_name.toLowerCase();
        const phone = client.phone?.toLowerCase() || '';
        const email = client.email?.toLowerCase() || '';
        
        return fullName.includes(searchLower) || phone.includes(searchLower) || email.includes(searchLower);
    });
});

onMounted(async () => {
    await calendarStore.fetchReferenceData();
});

const openCreateModal = () => {
    selectedClient.value = null;
    showModal.value = true;
};

const editClient = (client) => {
    selectedClient.value = client;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedClient.value = null;
};

const handleSaved = async () => {
    await calendarStore.fetchReferenceData();
};

const deleteClient = async (client) => {
    if (!confirm(`Удалить клиента ${client.full_name}?`)) return;
    
    try {
        await axios.delete(`/api/clients/${client.id}`);
        await calendarStore.fetchReferenceData();
    } catch (error) {
        alert('Ошибка при удалении клиента');
    }
};
</script>
