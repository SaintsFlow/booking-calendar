<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Может ли пользователь просматривать список бронирований
     */
    public function viewAny(User $user): bool
    {
        // Все роли могут просматривать бронирования (фильтр применяется в контроллере)
        return $user->tenant_id !== null;
    }

    /**
     * Может ли пользователь просматривать конкретное бронирование
     */
    public function view(User $user, Booking $booking): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $booking->tenant_id) {
            return false;
        }

        // Менеджер и админ видят все
        if ($user->hasManagerAccess()) {
            return true;
        }

        // Сотрудник видит только свои записи
        return $booking->employee_id === $user->id;
    }

    /**
     * Может ли пользователь создавать бронирования
     */
    public function create(User $user): bool
    {
        // Только менеджер и выше
        return $user->hasManagerAccess();
    }

    /**
     * Может ли пользователь обновлять бронирование
     */
    public function update(User $user, Booking $booking): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $booking->tenant_id) {
            return false;
        }

        // Менеджер и админ могут редактировать любые
        if ($user->hasManagerAccess()) {
            return true;
        }

        // Сотрудник не может редактировать бронирования
        return false;
    }

    /**
     * Может ли пользователь удалять/отменять бронирование
     */
    public function delete(User $user, Booking $booking): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $booking->tenant_id) {
            return false;
        }

        // Только менеджер и выше
        return $user->hasManagerAccess();
    }

    /**
     * Может ли пользователь переносить бронирование (drag&drop)
     */
    public function move(User $user, Booking $booking): bool
    {
        return $this->update($user, $booking);
    }

    /**
     * Может ли пользователь менять статус посещения клиента
     */
    public function updateAttendance(User $user, Booking $booking): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $booking->tenant_id) {
            return false;
        }

        // Менеджер и админ могут менять статус любой записи
        if ($user->hasManagerAccess()) {
            return true;
        }

        // Сотрудник может менять статус только своих записей
        return $booking->employee_id === $user->id;
    }

    /**
     * Может ли пользователь менять статус бронирования
     */
    public function updateStatus(User $user, Booking $booking): bool
    {
        // Проверка тенанта
        if ($user->tenant_id !== $booking->tenant_id) {
            return false;
        }

        // Менеджер и админ могут менять статус любой записи
        if ($user->hasManagerAccess()) {
            return true;
        }

        // Сотрудник может менять статус только своих записей
        return $booking->employee_id === $user->id;
    }
}
