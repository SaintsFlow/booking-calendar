<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center">
                    <div>
                        <Link :href="route('clients.index')" class="text-blue-600 hover:text-blue-800 text-sm">
                            ← Назад к списку
                        </Link>
                        <h1 class="text-2xl font-bold text-gray-900 mt-2">
                            {{ client?.first_name }} {{ client?.last_name }}
                        </h1>
                        <p class="text-gray-600 mt-1">{{ client?.phone }}</p>
                        <p v-if="client?.email" class="text-gray-600">{{ client.email }}</p>
                    </div>
                </div>
            </div>

            <!-- История бронирований -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">История бронирований</h2>
                
                <div v-if="bookings.length > 0" class="space-y-3">
                    <div
                        v-for="booking in bookings"
                        :key="booking.id"
                        class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition"
                    >
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-medium text-gray-900">
                                    {{ formatDate(booking.start_at) }}
                                </div>
                                <div class="text-sm text-gray-600 mt-1">
                                    {{ formatTime(booking.start_at) }} - {{ formatTime(booking.end_at) }}
                                </div>
                                <div class="text-sm text-gray-700 mt-2">
                                    Сотрудник: {{ booking.employee?.name }}
                                </div>
                                <div class="text-sm text-gray-700">
                                    Место: {{ booking.workplace?.name }}
                                </div>
                                <div class="text-sm text-gray-700 mt-1">
                                    Услуги: {{ booking.services?.map(s => s.name).join(', ') }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div
                                    class="inline-block px-3 py-1 rounded text-sm"
                                    :style="{ backgroundColor: booking.status?.color || '#e5e7eb' }"
                                >
                                    {{ booking.status?.name }}
                                </div>
                                <div class="text-lg font-bold text-gray-900 mt-2">
                                    {{ booking.total_price }} ₽
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div v-else class="text-center py-8 text-gray-500">
                    У клиента пока нет бронирований
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { ru } from 'date-fns/locale';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    clientId: {
        type: [String, Number],
        required: true,
    },
});

const client = ref(null);
const bookings = ref([]);

onMounted(async () => {
    try {
        const [clientRes, bookingsRes] = await Promise.all([
            axios.get(`/api/clients/${props.clientId}`),
            axios.get(`/api/bookings?client_id=${props.clientId}`),
        ]);
        
        client.value = clientRes.data;
        bookings.value = bookingsRes.data.data || bookingsRes.data;
    } catch (error) {
        console.error('Failed to load client data:', error);
    }
});

const formatDate = (datetime) => {
    return format(new Date(datetime), 'd MMMM yyyy', { locale: ru });
};

const formatTime = (datetime) => {
    return format(new Date(datetime), 'HH:mm');
};
</script>
