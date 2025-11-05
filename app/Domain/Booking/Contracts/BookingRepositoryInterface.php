<?php

namespace App\Domain\Booking\Contracts;

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface BookingRepositoryInterface
{
    /**
     * Получить список бронирований с фильтрами
     */
    public function paginate(array $filters = [], int $perPage = 50): LengthAwarePaginator;

    /**
     * Найти бронирование по ID
     */
    public function findById(int $id): ?Booking;

    /**
     * Найти бронирование по ID с загрузкой связей
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?Booking;

    /**
     * Создать бронирование
     */
    public function create(array $data): Booking;

    /**
     * Обновить бронирование
     */
    public function update(Booking $booking, array $data): Booking;

    /**
     * Удалить бронирование
     */
    public function delete(Booking $booking): bool;

    /**
     * Проверить конфликт времени
     */
    public function hasTimeConflict(
        int $employeeId,
        \Carbon\Carbon $startTime,
        \Carbon\Carbon $endTime,
        ?int $excludeBookingId = null
    ): bool;

    /**
     * Получить бронирования за период
     */
    public function getByDateRange(
        \Carbon\Carbon $startDate,
        \Carbon\Carbon $endDate,
        ?int $tenantId = null
    ): Collection;

    /**
     * Получить бронирования сотрудника за день
     */
    public function getEmployeeBookingsForDay(
        int $employeeId,
        \Carbon\Carbon $date
    ): Collection;

    /**
     * Получить бронирования клиента
     */
    public function getClientBookings(int $clientId, int $limit = 10): Collection;

    /**
     * Синхронизировать услуги
     */
    public function syncServices(Booking $booking, array $services): void;
}
