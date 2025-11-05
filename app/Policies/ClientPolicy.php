<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /**
     * Может ли пользователь просматривать клиента
     */
    public function view(User $user, Client $client): bool
    {
        // Проверка тенанта
        return $user->tenant_id === $client->tenant_id;
    }

    /**
     * Может ли пользователь обновлять клиента
     */
    public function update(User $user, Client $client): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $client->tenant_id) {
            return false;
        }

        // Только менеджер и выше
        return $user->hasManagerAccess();
    }

    /**
     * Может ли пользователь удалять клиента
     */
    public function delete(User $user, Client $client): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $client->tenant_id) {
            return false;
        }

        // Только администратор и выше
        return $user->hasAdminAccess();
    }
}
