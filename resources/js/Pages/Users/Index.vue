<template>
    <Head title="Сотрудники" />
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Сотрудники</h1>
                    <button
                        @click="openCreateModal"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        Добавить сотрудника
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Имя</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Роль</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Места работы</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Действия</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="user in calendarStore.employees" :key="user.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ user.name }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ user.email }}</td>
                            <td class="px-6 py-4">
                                <span
                                    :class="{
                                        'bg-purple-100 text-purple-800': user.role === 'admin',
                                        'bg-blue-100 text-blue-800': user.role === 'manager',
                                        'bg-green-100 text-green-800': user.role === 'employee',
                                    }"
                                    class="px-2 py-1 text-xs font-medium rounded"
                                >
                                    {{ getRoleLabel(user.role) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <div v-if="user.workplaces && user.workplaces.length > 0">
                                    {{ user.workplaces.map(w => w.name).join(', ') }}
                                </div>
                                <span v-else class="text-gray-400">Не назначено</span>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex gap-3">
                                    <button @click="editUser(user)" class="text-blue-600 hover:text-blue-800">
                                        Редактировать
                                    </button>
                                    <button @click="editWorkingHours(user)" class="text-indigo-600 hover:text-indigo-800">
                                        График
                                    </button>
                                    <button @click="manageVacations(user)" class="text-green-600 hover:text-green-800">
                                        Отпуска
                                    </button>
                                    <button
                                        @click="deleteUser(user)"
                                        :disabled="user.id === $page.props.auth.user?.id"
                                        :class="user.id === $page.props.auth.user?.id ? 'text-gray-400 cursor-not-allowed' : 'text-red-600 hover:text-red-800'"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="calendarStore.employees.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                Сотрудники не найдены
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- User Modal -->
        <UserModal
            :show="showModal"
            :user="selectedUser"
            @close="closeModal"
            @saved="handleSaved"
        />

        <!-- Working Hours Modal -->
        <WorkingHoursModal
            :show="showWorkingHoursModal"
            :title="`График работы - ${selectedUserForSchedule?.name || ''}`"
            :initialSchedule="selectedUserForSchedule?.working_hours"
            @close="closeWorkingHoursModal"
            @save="saveUserWorkingHours"
        />

        <!-- Vacations Modal -->
        <VacationsModal
            :show="showVacationsModal"
            :employee="selectedUserForVacations"
            @close="closeVacationsModal"
            @saved="handleVacationsSaved"
        />
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import UserModal from '@/Components/Modals/UserModal.vue';
import WorkingHoursModal from '@/Components/Modals/WorkingHoursModal.vue';
import VacationsModal from '@/Components/Modals/VacationsModal.vue';
import { useCalendarStore } from '@/stores/calendar';
import { useToast } from '@/composables/useToast';
import axios from 'axios';

const calendarStore = useCalendarStore();
const toast = useToast();

const showModal = ref(false);
const selectedUser = ref(null);
const showWorkingHoursModal = ref(false);
const selectedUserForSchedule = ref(null);
const showVacationsModal = ref(false);
const selectedUserForVacations = ref(null);

onMounted(async () => {
    await calendarStore.fetchReferenceData();
});

const getRoleLabel = (role) => {
    const labels = {
        admin: 'Администратор',
        manager: 'Менеджер',
        employee: 'Сотрудник',
    };
    return labels[role] || role;
};

const openCreateModal = () => {
    selectedUser.value = null;
    showModal.value = true;
};

const editUser = (user) => {
    selectedUser.value = user;
    showModal.value = true;
};

const editWorkingHours = (user) => {
    selectedUserForSchedule.value = user;
    showWorkingHoursModal.value = true;
};

const manageVacations = (user) => {
    selectedUserForVacations.value = user;
    showVacationsModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedUser.value = null;
};

const closeWorkingHoursModal = () => {
    showWorkingHoursModal.value = false;
    selectedUserForSchedule.value = null;
};

const closeVacationsModal = () => {
    showVacationsModal.value = false;
    selectedUserForVacations.value = null;
};

const saveUserWorkingHours = async (schedule) => {
    try {
        await axios.put(`/api/users/${selectedUserForSchedule.value.id}`, {
            working_hours: schedule
        });
        
        toast.success('Успешно', 'График работы сохранен');
        await calendarStore.fetchReferenceData();
        closeWorkingHoursModal();
    } catch (error) {
        console.error('Error saving working hours:', error);
        toast.error('Ошибка', 'Не удалось сохранить график работы');
    }
};

const handleVacationsSaved = async () => {
    await calendarStore.fetchReferenceData();
};

const handleSaved = async () => {
    await calendarStore.fetchReferenceData();
};

const deleteUser = async (user) => {
    if (!confirm(`Удалить сотрудника ${user.name}?`)) return;
    
    try {
        await axios.delete(`/api/users/${user.id}`);
        await calendarStore.fetchReferenceData();
    } catch (error) {
        alert('Ошибка при удалении сотрудника');
    }
};
</script>
