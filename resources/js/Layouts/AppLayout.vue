<template>
    <div class="min-h-screen bg-gray-100">
        <!-- Navigation -->
        <nav class="bg-white border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-8">
                <div class="flex justify-between h-14 sm:h-16">
                    <div class="flex">
                        <!-- Logo -->
                        <div class="shrink-0 flex items-center">
                            <Link :href="route('home')" class="text-base sm:text-xl font-bold text-blue-600">
                                {{ $page.props.auth.tenant.name }}
                            </Link>
                        </div>

                        <!-- Navigation Links -->
                        <div class="hidden space-x-2 md:space-x-8 sm:-my-px sm:ml-4 md:ml-10 sm:flex">
                            <!-- Для супер-админа показываем только Кабинеты -->
                            <template v-if="$page.props.auth.user?.is_super_admin">
                                <NavLink :href="route('admin.tenants')" :active="route().current('admin.*')">
                                    Кабинеты
                                </NavLink>
                            </template>
                            
                            <!-- Для сотрудников показываем только Календарь -->
                            <template v-else-if="$page.props.auth.user?.is_employee">
                                <NavLink :href="route('calendar')" :active="route().current('calendar')">
                                    Календарь
                                </NavLink>
                            </template>
                            
                            <!-- Для остальных пользователей (админы и менеджеры) показываем все меню -->
                            <template v-else>
                                <NavLink :href="route('calendar')" :active="route().current('calendar')">
                                    Календарь
                                </NavLink>
                                <NavLink :href="route('clients.index')" :active="route().current('clients.*')">
                                    Клиенты
                                </NavLink>
                                <NavLink :href="route('services.index')" :active="route().current('services.*')">
                                    Услуги
                                </NavLink>
                                <NavLink v-if="$page.props.auth.user?.has_admin_access" :href="route('users.index')" :active="route().current('users.*')">
                                    Сотрудники
                                </NavLink>
                                <NavLink v-if="$page.props.auth.user?.has_admin_access" :href="route('statuses.index')" :active="route().current('statuses.*')">
                                    Статусы
                                </NavLink>
                                <NavLink v-if="$page.props.auth.user?.has_admin_access" :href="route('workplaces.index')" :active="route().current('workplaces.*')">
                                    Места работы
                                </NavLink>
                                <NavLink v-if="$page.props.auth.user?.has_admin_access" :href="route('settings.bitrix24')" :active="route().current('settings.*')">
                                    ⚙️ Настройки
                                </NavLink>
                            </template>
                        </div>
                    </div>

                    <!-- User Menu -->
                    <div class="flex items-center">
                        <div class="ml-1 sm:ml-3 relative">
                            <div class="flex items-center space-x-1 sm:space-x-3">
                                <span class="text-xs sm:text-sm text-gray-700 max-w-[100px] sm:max-w-none font-bold truncate">
                                    {{ $page.props.auth.user?.name }}
                                </span>
                                <button @click="logout" class="text-xs sm:text-sm text-gray-700 hover:text-gray-900 whitespace-nowrap cursor-pointer">
                                    Выйти
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="py-3 sm:py-6">
            <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
                <slot />
            </div>
        </main>

        <!-- Toast Notifications (выше модалок) -->
        <div class="fixed top-2 right-2 sm:top-4 sm:right-4 space-y-2 max-w-[calc(100vw-1rem)] sm:max-w-md" style="z-index: 9999;">
            <Toast
                v-for="toast in toasts"
                :key="toast.id"
                :show="toast.show"
                :type="toast.type"
                :title="toast.title"
                :message="toast.message"
                :duration="toast.duration"
                @close="removeToast(toast.id)"
            />
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import NavLink from '@/Components/NavLink.vue';
import Toast from '@/Components/Toast.vue';
import { useToast } from '@/composables/useToast';

const { toasts, removeToast } = useToast();

const logout = () => {
    router.post(route('logout'));
};
</script>
