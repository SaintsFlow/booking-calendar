<?php

namespace App\Application\Booking\Services;

use App\Application\Booking\DTOs\CreateBookingDTO;
use App\Application\Booking\DTOs\UpdateBookingDTO;
use App\Domain\Booking\Contracts\BookingRepositoryInterface;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository
    ) {}

    /**
     * Получить список бронирований с фильтрами
     */
    public function getBookings(array $filters = [], int $perPage = 50)
    {
        return $this->bookingRepository->paginate($filters, $perPage);
    }

    /**
     * Получить бронирование по ID с загрузкой связей
     */
    public function getBookingById(int $id): ?Booking
    {
        return $this->bookingRepository->findByIdWithRelations($id);
    }

    /**
     * Создать бронирование
     */
    public function createBooking(CreateBookingDTO $dto): Booking
    {
        return DB::transaction(function () use ($dto) {
            // Создаём бронирование
            $booking = $this->bookingRepository->create($dto->toArray());

            // Синхронизируем услуги
            if (!empty($dto->services)) {
                $this->bookingRepository->syncServices($booking, $dto->services);
            }

            // Загружаем связи для ответа
            return $this->bookingRepository->findByIdWithRelations($booking->id);
        });
    }

    /**
     * Обновить бронирование
     */
    public function updateBooking(Booking $booking, UpdateBookingDTO $dto): Booking
    {
        return DB::transaction(function () use ($booking, $dto) {
            // Обновляем основные данные
            $updateData = $dto->toArray();
            if (!empty($updateData)) {
                $booking = $this->bookingRepository->update($booking, $updateData);
            }

            // Синхронизируем услуги если переданы
            if ($dto->services !== null) {
                $this->bookingRepository->syncServices($booking, $dto->services);
            }

            // Загружаем обновлённые связи
            return $this->bookingRepository->findByIdWithRelations($booking->id);
        });
    }

    /**
     * Удалить бронирование
     */
    public function deleteBooking(Booking $booking): bool
    {
        return $this->bookingRepository->delete($booking);
    }

    /**
     * Проверить конфликт времени
     */
    public function hasTimeConflict(
        int $employeeId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeBookingId = null
    ): bool {
        return $this->bookingRepository->hasTimeConflict(
            $employeeId,
            $startTime,
            $endTime,
            $excludeBookingId
        );
    }

    /**
     * Получить бронирования за период
     */
    public function getBookingsByDateRange(
        Carbon $startDate,
        Carbon $endDate,
        ?int $tenantId = null
    ) {
        return $this->bookingRepository->getByDateRange($startDate, $endDate, $tenantId);
    }

    /**
     * Рассчитать длительность и стоимость по услугам
     */
    public function calculateBookingMetrics(array $serviceIds): array
    {
        $services = Service::whereIn('id', $serviceIds)->get();

        return [
            'duration_minutes' => $services->sum('duration_minutes'),
            'total_price' => $services->sum('price'),
            'services' => $services,
        ];
    }

    /**
     * Получить дефолтный статус для тенанта
     */
    public function getDefaultStatus(int $tenantId): ?Status
    {
        return Status::forTenant($tenantId)->default()->first()
            ?? Status::forTenant($tenantId)->where('code', 'confirmed')->first();
    }

    /**
     * Получить бронирования клиента
     */
    public function getClientBookings(int $clientId, int $limit = 10)
    {
        return $this->bookingRepository->getClientBookings($clientId, $limit);
    }
}
