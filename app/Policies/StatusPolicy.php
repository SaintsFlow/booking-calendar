<?php

namespace App\Policies;

use App\Models\Status;
use App\Models\User;

class StatusPolicy
{
    /**
     * Может ли пользователь создавать статусы
     */
    public function create(User $user): bool
    {
        // Только администратор и выше
        return $user->hasAdminAccess();
    }

    /**
     * Может ли пользователь обновлять статус
     */
    public function update(User $user, Status $status): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $status->tenant_id) {
            return false;
        }

        // Только администратор и выше
        return $user->hasAdminAccess();
    }

    /**
     * Может ли пользователь удалять статус
     */
    public function delete(User $user, Status $status): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $status->tenant_id) {
            return false;
        }

        // Только администратор и выше
        return $user->hasAdminAccess();
    }
}
