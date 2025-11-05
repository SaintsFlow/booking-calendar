<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workplace;

class WorkplacePolicy
{
    /**
     * Может ли пользователь создавать места работы
     */
    public function create(User $user): bool
    {
        // Только администратор и выше
        return $user->hasAdminAccess();
    }

    /**
     * Может ли пользователь обновлять место работы
     */
    public function update(User $user, Workplace $workplace): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $workplace->tenant_id) {
            return false;
        }

        // Только администратор и выше
        return $user->hasAdminAccess();
    }

    /**
     * Может ли пользователь удалять место работы
     */
    public function delete(User $user, Workplace $workplace): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $workplace->tenant_id) {
            return false;
        }

        // Только администратор и выше
        return $user->hasAdminAccess();
    }
}
