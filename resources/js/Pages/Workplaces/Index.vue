<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Места работы</h1>
                    <button
                        @click="openCreateModal"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        Добавить место работы
                    </button>
                </div>
            </div>

            <!-- Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="workplace in calendarStore.workplaces"
                    :key="workplace.id"
                    class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ workplace.name }}</h3>
                        </div>
                        <span
                            :class="workplace.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                            class="px-2 py-1 text-xs font-medium rounded"
                        >
                            {{ workplace.is_active ? 'Активно' : 'Неактивно' }}
                        </span>
                    </div>

                    <div v-if="workplace.description" class="text-sm text-gray-700 mb-4">
                        {{ workplace.description }}
                    </div>

                    <!-- График работы (если установлен) -->
                    <div v-if="workplace.working_hours" class="mb-4 p-2 bg-gray-50 rounded text-xs">
                        <p class="font-medium text-gray-700 mb-1">График работы:</p>
                        <div class="space-y-0.5">
                            <div v-for="(hours, day) in workplace.working_hours" :key="day" class="text-gray-600">
                                <span v-if="hours.is_working">
                                    {{ getDayLabel(day) }}: {{ hours.start }} - {{ hours.end }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <button @click="editWorkplace(workplace)" class="text-blue-600 hover:text-blue-800 text-sm">
                            Редактировать
                        </button>
                        <button @click="editWorkingHours(workplace)" class="text-indigo-600 hover:text-indigo-800 text-sm">
                            График работы
                        </button>
                        <button @click="deleteWorkplace(workplace)" class="text-red-600 hover:text-red-800 text-sm">
                            Удалить
                        </button>
                    </div>
                </div>

                <div v-if="calendarStore.workplaces.length === 0" class="col-span-full text-center py-12 text-gray-500">
                    Места работы не найдены
                </div>
            </div>
        </div>

        <!-- Workplace Modal -->
        <WorkplaceModal
            :show="showModal"
            :workplace="selectedWorkplace"
            @close="closeModal"
            @saved="handleSaved"
        />

        <!-- Working Hours Modal -->
        <WorkingHoursModal
            :show="showWorkingHoursModal"
            :title="`График работы - ${selectedWorkplaceForSchedule?.name || ''}`"
            :initialSchedule="selectedWorkplaceForSchedule?.working_hours"
            @close="closeWorkingHoursModal"
            @save="saveWorkingHours"
        />
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import WorkplaceModal from '@/Components/Modals/WorkplaceModal.vue';
import WorkingHoursModal from '@/Components/Modals/WorkingHoursModal.vue';
import { useCalendarStore } from '@/stores/calendar';
import { useToast } from '@/composables/useToast';
import axios from 'axios';

const calendarStore = useCalendarStore();
const toast = useToast();

const showModal = ref(false);
const selectedWorkplace = ref(null);
const showWorkingHoursModal = ref(false);
const selectedWorkplaceForSchedule = ref(null);

const dayLabels = {
    monday: 'Пн',
    tuesday: 'Вт',
    wednesday: 'Ср',
    thursday: 'Чт',
    friday: 'Пт',
    saturday: 'Сб',
    sunday: 'Вс'
};

const getDayLabel = (day) => dayLabels[day] || day;

onMounted(async () => {
    await calendarStore.fetchReferenceData();
});

const openCreateModal = () => {
    selectedWorkplace.value = null;
    showModal.value = true;
};

const editWorkplace = (workplace) => {
    selectedWorkplace.value = workplace;
    showModal.value = true;
};

const editWorkingHours = (workplace) => {
    selectedWorkplaceForSchedule.value = workplace;
    showWorkingHoursModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedWorkplace.value = null;
};

const closeWorkingHoursModal = () => {
    showWorkingHoursModal.value = false;
    selectedWorkplaceForSchedule.value = null;
};

const saveWorkingHours = async (schedule) => {
    try {
        await axios.put(`/api/workplaces/${selectedWorkplaceForSchedule.value.id}`, {
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

const handleSaved = async () => {
    await calendarStore.fetchReferenceData();
};

const deleteWorkplace = async (workplace) => {
    if (!confirm(`Удалить место работы "${workplace.name}"?`)) return;
    
    try {
        await axios.delete(`/api/workplaces/${workplace.id}`);
        await calendarStore.fetchReferenceData();
    } catch (error) {
        alert('Ошибка при удалении места работы');
    }
};
</script>
