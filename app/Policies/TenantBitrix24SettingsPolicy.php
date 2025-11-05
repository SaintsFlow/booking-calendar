<?php

namespace App\Policies;

use App\Models\TenantBitrix24Settings;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TenantBitrix24SettingsPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Только администраторы могут просматривать настройки
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TenantBitrix24Settings $tenantBitrix24Settings): bool
    {
        // Только администраторы своего тенанта
        return $user->hasAdminAccess() && $user->tenant_id === $tenantBitrix24Settings->tenant_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Только администраторы могут создавать настройки
        return $user->hasAdminAccess();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TenantBitrix24Settings $tenantBitrix24Settings): bool
    {
        // Только администраторы своего тенанта
        return $user->hasAdminAccess() && $user->tenant_id === $tenantBitrix24Settings->tenant_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TenantBitrix24Settings $tenantBitrix24Settings): bool
    {
        // Только администраторы своего тенанта
        return $user->hasAdminAccess() && $user->tenant_id === $tenantBitrix24Settings->tenant_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TenantBitrix24Settings $tenantBitrix24Settings): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TenantBitrix24Settings $tenantBitrix24Settings): bool
    {
        return false;
    }
}
