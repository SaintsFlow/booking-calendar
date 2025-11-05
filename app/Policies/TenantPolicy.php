<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    /**
     * Только супер-админ может просматривать список тенантов
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Только супер-админ может создавать тенантов
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Только супер-админ может просматривать любого тенанта
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Только супер-админ может обновлять тенантов
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Только супер-админ может удалять тенантов
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Только супер-админ может управлять подпиской
     */
    public function manageSubscription(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Только супер-админ может impersonate
     */
    public function impersonate(User $user, Tenant $tenant): bool
    {
        return $user->isSuperAdmin();
    }
}
