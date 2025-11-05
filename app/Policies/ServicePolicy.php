<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    /**
     * Может ли пользователь создавать услуги
     */
    public function create(User $user): bool
    {
        // Только менеджер и выше
        return $user->hasManagerAccess();
    }

    /**
     * Может ли пользователь обновлять услугу
     */
    public function update(User $user, Service $service): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $service->tenant_id) {
            return false;
        }

        // Только менеджер и выше
        return $user->hasManagerAccess();
    }

    /**
     * Может ли пользователь удалять услугу
     */
    public function delete(User $user, Service $service): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $service->tenant_id) {
            return false;
        }

        // Только администратор и выше
        return $user->hasAdminAccess();
    }
}
