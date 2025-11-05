<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Может ли пользователь просматривать список пользователей
     */
    public function viewAny(User $user): bool
    {
        // Супер-админ видит всех
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Все роли могут видеть список (с фильтром в контроллере)
        return $user->tenant_id !== null;
    }

    /**
     * Может ли пользователь просматривать конкретного пользователя
     */
    public function view(User $user, User $model): bool
    {
        // Супер-админ видит всех
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Проверка тенанта
        return $user->tenant_id === $model->tenant_id;
    }

    /**
     * Может ли пользователь создавать пользователей
     */
    public function create(User $user): bool
    {
        // Только администратор и выше
        return $user->hasAdminAccess();
    }

    /**
     * Может ли пользователь обновлять пользователя
     */
    public function update(User $user, User $model): bool
    {
        // Супер-админ может обновлять всех
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Проверка тенанта
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        // Администратор может обновлять пользователей своего тенанта
        if ($user->hasAdminAccess()) {
            return true;
        }

        // Пользователь может редактировать только себя
        return $user->id === $model->id;
    }

    /**
     * Может ли пользователь удалять пользователя
     */
    public function delete(User $user, User $model): bool
    {
        // Супер-админ может удалять всех
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Проверка тенанта
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        // Нельзя удалить самого себя
        if ($user->id === $model->id) {
            return false;
        }

        // Только администратор может удалять
        return $user->hasAdminAccess();
    }

    /**
     * Может ли пользователь управлять ролями
     */
    public function manageRoles(User $user, User $model): bool
    {
        // Супер-админ может управлять всеми ролями
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Проверка тенанта
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        // Только администратор может управлять ролями
        return $user->hasAdminAccess();
    }
}
