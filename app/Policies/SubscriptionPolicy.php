<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubscriptionPolicy
{
    /**
     * Determine whether the user can view any models.
     * Только супер-админ может просматривать все подписки
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    /**
     * Determine whether the user can view the model.
     * Только супер-админ может просматривать подписку
     */
    public function view(User $user, Subscription $subscription): bool
    {
        return $user->role === 'super_admin';
    }

    /**
     * Determine whether the user can create models.
     * Только супер-админ может создавать подписки
     */
    public function create(User $user): bool
    {
        return $user->role === 'super_admin';
    }

    /**
     * Determine whether the user can update the model.
     * Только супер-админ может обновлять подписки
     */
    public function update(User $user, Subscription $subscription): bool
    {
        return $user->role === 'super_admin';
    }

    /**
     * Determine whether the user can delete the model.
     * Только супер-админ может удалять подписки
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        return $user->role === 'super_admin';
    }

    /**
     * Определяет, может ли пользователь приостановить подписку
     */
    public function pause(User $user, Subscription $subscription): bool
    {
        return $user->role === 'super_admin';
    }

    /**
     * Определяет, может ли пользователь возобновить подписку
     */
    public function resume(User $user, Subscription $subscription): bool
    {
        return $user->role === 'super_admin';
    }

    /**
     * Определяет, может ли пользователь отменить подписку
     */
    public function cancel(User $user, Subscription $subscription): bool
    {
        return $user->role === 'super_admin';
    }
}
