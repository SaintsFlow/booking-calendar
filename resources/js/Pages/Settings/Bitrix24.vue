<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    settings: Object,
});

const form = useForm({
    enabled: props.settings.enabled,
    webhook_url: '',
    oauth_client_id: '',
    oauth_client_secret: '',
    catalog_iblock_id: props.settings.catalog_iblock_id,
    contact_type_id: props.settings.contact_type_id,
    contact_source_id: props.settings.contact_source_id,
    contact_honorific: props.settings.contact_honorific || '',
    contact_opened: props.settings.contact_opened,
    deal_category_id: props.settings.deal_category_id,
    deal_stage_id: props.settings.deal_stage_id,
    deal_type_id: props.settings.deal_type_id,
    deal_source_id: props.settings.deal_source_id,
    deal_currency_id: props.settings.deal_currency_id,
    deal_opened: props.settings.deal_opened,
    deal_probability: props.settings.deal_probability,
    max_contacts_for_deal_search: props.settings.max_contacts_for_deal_search,
    max_duplicate_values: props.settings.max_duplicate_values,
});

const showWebhookInput = ref(false);
const showOAuthClientIdInput = ref(false);
const showOAuthClientSecretInput = ref(false);
const testing = ref(false);
const testResult = ref(null);
const syncing = ref(false);
const syncResult = ref(null);
const syncingUsers = ref(false);
const syncUsersResult = ref(null);
const syncingServicesTo = ref(false);
const syncServicesToResult = ref(null);

const hasWebhook = computed(() => props.settings.webhook_url !== null);
const hasOAuthClientId = computed(() => props.settings.oauth_client_id !== null);
const hasOAuthClientSecret = computed(() => props.settings.oauth_client_secret !== null);

const submit = () => {
    form.put('/settings/bitrix24', {
        preserveScroll: true,
        onSuccess: () => {
            showWebhookInput.value = false;
            showOAuthClientIdInput.value = false;
            showOAuthClientSecretInput.value = false;
            form.webhook_url = '';
            form.oauth_client_id = '';
            form.oauth_client_secret = '';
        },
    });
};

const testConnection = async () => {
    testing.value = true;
    testResult.value = null;

    try {
        const response = await fetch('/settings/bitrix24/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });

        const data = await response.json();
        testResult.value = data;
    } catch (error) {
        testResult.value = {
            success: false,
            message: 'Ошибка соединения: ' + error.message,
        };
    } finally {
        testing.value = false;
    }
};

const syncProducts = async () => {
    syncing.value = true;
    syncResult.value = null;

    try {
        const response = await fetch('/settings/bitrix24/sync-products', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });

        const data = await response.json();
        syncResult.value = data;
    } catch (error) {
        syncResult.value = {
            success: false,
            message: 'Ошибка синхронизации: ' + error.message,
        };
    } finally {
        syncing.value = false;
    }
};

const syncUsers = async () => {
    syncingUsers.value = true;
    syncUsersResult.value = null;

    try {
        const response = await fetch('/settings/bitrix24/sync-users', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });

        const data = await response.json();
        syncUsersResult.value = data;
    } catch (error) {
        syncUsersResult.value = {
            success: false,
            message: 'Ошибка синхронизации: ' + error.message,
        };
    } finally {
        syncingUsers.value = false;
    }
};

const syncServicesToBitrix24 = async () => {
    syncingServicesTo.value = true;
    syncServicesToResult.value = null;

    try {
        const response = await fetch('/settings/bitrix24/sync-services-to', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
        });

        const data = await response.json();
        syncServicesToResult.value = data;
    } catch (error) {
        syncServicesToResult.value = {
            success: false,
            message: 'Ошибка синхронизации: ' + error.message,
        };
    } finally {
        syncingServicesTo.value = false;
    }
};
</script>

<template>
    <Head title="Настройки Bitrix24" />

    <AppLayout>
        <div class="max-w-4xl mx-auto">
            <!-- Заголовок -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Интеграция с Bitrix24</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Настройте подключение к вашему порталу Bitrix24 для автоматической синхронизации клиентов и сделок
                </p>
            </div>

            <!-- Основная форма -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Карточка: Основные настройки -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Основные настройки</h2>

                    <!-- Включить интеграцию -->
                    <div class="flex items-center mb-6">
                        <input
                            v-model="form.enabled"
                            type="checkbox"
                            id="enabled"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label for="enabled" class="ml-2 block text-sm text-gray-900">
                            Включить интеграцию с Bitrix24
                        </label>
                    </div>

                    <!-- Webhook URL -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Webhook URL
                        </label>
                        
                        <div v-if="!showWebhookInput && hasWebhook" class="flex items-center gap-3">
                            <div class="flex-1 px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm text-gray-500">
                                ****************************************
                            </div>
                            <button
                                type="button"
                                @click="showWebhookInput = true"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md text-sm font-medium"
                            >
                                Изменить
                            </button>
                        </div>

                        <div v-else>
                            <input
                                v-model="form.webhook_url"
                                type="url"
                                placeholder="https://your-domain.bitrix24.ru/rest/1/webhook_code/"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                :class="{ 'border-red-500': form.errors.webhook_url }"
                            />
                            <p v-if="form.errors.webhook_url" class="mt-1 text-sm text-red-600">
                                {{ form.errors.webhook_url }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                Получите вебхук в разделе "Разработчикам" → "Вебхуки" в вашем Bitrix24
                            </p>
                        </div>
                    </div>

                    <!-- OAuth Client ID -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            OAuth Client ID
                        </label>
                        
                        <div v-if="!showOAuthClientIdInput && hasOAuthClientId" class="flex items-center gap-3">
                            <div class="flex-1 px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm text-gray-500">
                                ****************************************
                            </div>
                            <button
                                type="button"
                                @click="showOAuthClientIdInput = true"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md text-sm font-medium"
                            >
                                Изменить
                            </button>
                        </div>

                        <div v-else>
                            <input
                                v-model="form.oauth_client_id"
                                type="text"
                                placeholder="local.XXXXXXXXX.XXXXXXXXX"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                :class="{ 'border-red-500': form.errors.oauth_client_id }"
                            />
                            <p v-if="form.errors.oauth_client_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.oauth_client_id }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                Для OAuth авторизации. Создайте локальное приложение в Bitrix24
                            </p>
                        </div>
                    </div>

                    <!-- OAuth Client Secret -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            OAuth Client Secret
                        </label>
                        
                        <div v-if="!showOAuthClientSecretInput && hasOAuthClientSecret" class="flex items-center gap-3">
                            <div class="flex-1 px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-sm text-gray-500">
                                ****************************************
                            </div>
                            <button
                                type="button"
                                @click="showOAuthClientSecretInput = true"
                                class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-md text-sm font-medium"
                            >
                                Изменить
                            </button>
                        </div>

                        <div v-else>
                            <input
                                v-model="form.oauth_client_secret"
                                type="password"
                                placeholder="XXXXXXXXXXXXXXXXXXXXXXXXXX"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                :class="{ 'border-red-500': form.errors.oauth_client_secret }"
                            />
                            <p v-if="form.errors.oauth_client_secret" class="mt-1 text-sm text-red-600">
                                {{ form.errors.oauth_client_secret }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                Секретный ключ для OAuth авторизации
                            </p>
                        </div>

                        <!-- Catalog IBlock ID -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                ID торгового каталога (IBlock)
                            </label>
                            <input
                                v-model.number="form.catalog_iblock_id"
                                type="number"
                                placeholder="23"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="{ 'border-red-500': form.errors.catalog_iblock_id }"
                            />
                            <p v-if="form.errors.catalog_iblock_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.catalog_iblock_id }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                ID информационного блока торгового каталога для синхронизации товаров/услуг
                            </p>
                        </div>
                    </div>

                    <!-- Тест подключения и синхронизация -->
                    <div v-if="hasWebhook" class="mt-4 flex gap-3">
                        <button
                            type="button"
                            @click="testConnection"
                            :disabled="testing"
                            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="testing">Проверка...</span>
                            <span v-else>Проверить подключение</span>
                        </button>

                        <button
                            v-if="form.catalog_iblock_id"
                            type="button"
                            @click="syncProducts"
                            :disabled="syncing"
                            class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-md text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="syncing">Синхронизация...</span>
                            <span v-else>← Импорт товаров</span>
                        </button>

                        <button
                            v-if="form.catalog_iblock_id"
                            type="button"
                            @click="syncServicesToBitrix24"
                            :disabled="syncingServicesTo"
                            class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-md text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="syncingServicesTo">Синхронизация...</span>
                            <span v-else>Экспорт услуг →</span>
                        </button>

                        <button
                            type="button"
                            @click="syncUsers"
                            :disabled="syncingUsers"
                            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-md text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="syncingUsers">Синхронизация...</span>
                            <span v-else>← Импорт пользователей</span>
                        </button>
                    </div>

                    <!-- Результаты -->
                    <div class="mt-3 space-y-2">
                        <!-- Результат теста -->
                        <div v-if="testResult" class="p-3 rounded-md" :class="{
                            'bg-green-50 border border-green-200': testResult.success,
                            'bg-red-50 border border-red-200': !testResult.success
                        }">
                            <p class="text-sm" :class="{
                                'text-green-800': testResult.success,
                                'text-red-800': !testResult.success
                            }">
                                {{ testResult.message }}
                            </p>
                        </div>

                        <!-- Результат синхронизации -->
                        <div v-if="syncResult" class="p-3 rounded-md" :class="{
                            'bg-green-50 border border-green-200': syncResult.success,
                            'bg-red-50 border border-red-200': !syncResult.success
                        }">
                            <p class="text-sm" :class="{
                                'text-green-800': syncResult.success,
                                'text-red-800': !syncResult.success
                            }">
                                {{ syncResult.message }}
                            </p>
                        </div>

                        <!-- Результат синхронизации пользователей -->
                        <div v-if="syncUsersResult" class="p-3 rounded-md" :class="{
                            'bg-green-50 border border-green-200': syncUsersResult.success,
                            'bg-red-50 border border-red-200': !syncUsersResult.success
                        }">
                            <p class="text-sm" :class="{
                                'text-green-800': syncUsersResult.success,
                                'text-red-800': !syncUsersResult.success
                            }">
                                {{ syncUsersResult.message }}
                            </p>
                        </div>

                        <!-- Результат синхронизации услуг в Bitrix24 -->
                        <div v-if="syncServicesToResult" class="p-3 rounded-md" :class="{
                            'bg-green-50 border border-green-200': syncServicesToResult.success,
                            'bg-red-50 border border-red-200': !syncServicesToResult.success
                        }">
                            <p class="text-sm" :class="{
                                'text-green-800': syncServicesToResult.success,
                                'text-red-800': !syncServicesToResult.success
                            }">
                                {{ syncServicesToResult.message }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Карточка: Настройки контакта -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Настройки контакта</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Тип контакта
                            </label>
                            <input
                                v-model="form.contact_type_id"
                                type="text"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Источник
                            </label>
                            <input
                                v-model="form.contact_source_id"
                                type="text"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Обращение (необязательно)
                            </label>
                            <input
                                v-model="form.contact_honorific"
                                type="text"
                                placeholder="Господин, Госпожа"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Доступен для всех
                            </label>
                            <select
                                v-model="form.contact_opened"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="Y">Да</option>
                                <option value="N">Нет</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Карточка: Настройки сделки -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Настройки сделки</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                ID категории (направления)
                            </label>
                            <input
                                v-model.number="form.deal_category_id"
                                type="number"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                0 = общая воронка
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Стадия сделки
                            </label>
                            <input
                                v-model="form.deal_stage_id"
                                type="text"
                                placeholder="NEW или C152:NEW"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">
                                Формат: NEW или C[ID]:NEW для категории
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Тип сделки
                            </label>
                            <input
                                v-model="form.deal_type_id"
                                type="text"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Источник
                            </label>
                            <input
                                v-model="form.deal_source_id"
                                type="text"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Валюта
                            </label>
                            <input
                                v-model="form.deal_currency_id"
                                type="text"
                                placeholder="RUB, USD, EUR, KZT"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Вероятность успеха (%)
                            </label>
                            <input
                                v-model.number="form.deal_probability"
                                type="number"
                                min="0"
                                max="100"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Доступна для всех
                            </label>
                            <select
                                v-model="form.deal_opened"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="Y">Да</option>
                                <option value="N">Нет</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Карточка: Лимиты -->
                <div class="bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Лимиты поиска</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Макс. контактов для поиска сделок
                            </label>
                            <input
                                v-model.number="form.max_contacts_for_deal_search"
                                type="number"
                                min="1"
                                max="50"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Макс. дубликатов для проверки
                            </label>
                            <input
                                v-model.number="form.max_duplicate_values"
                                type="number"
                                min="1"
                                max="50"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- Кнопки действий -->
                <div class="flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="router.visit('/')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Отмена
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span v-if="form.processing">Сохранение...</span>
                        <span v-else>Сохранить настройки</span>
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
