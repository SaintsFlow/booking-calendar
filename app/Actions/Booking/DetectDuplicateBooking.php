<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DetectDuplicateBooking
{
    /**
     * Проверить, существует ли дубликат бронирования
     *
     * @param int $clientId ID клиента
     * @param Carbon $startTime Время начала
     * @param Carbon $endTime Время окончания
     * @param array $serviceIds Массив ID услуг
     * @param int|null $excludeBookingId ID бронирования для исключения (при обновлении)
     * @return Booking|null Найденное дублирующее бронирование или null
     */
    public function detect(
        int $clientId,
        Carbon $startTime,
        Carbon $endTime,
        array $serviceIds,
        ?int $excludeBookingId = null
    ): ?Booking {
        // Сортируем массив услуг для корректного сравнения
        sort($serviceIds);

        // Ищем бронирования для этого клиента с пересечением времени
        $query = Booking::where('client_id', $clientId)
            ->where(function ($q) use ($startTime, $endTime) {
                // Проверка пересечения временных интервалов
                // Бронь пересекается, если:
                // (начало новой <= конец существующей) И (конец новой >= начало существующей)
                $q->where(function ($subQ) use ($startTime, $endTime) {
                    $subQ->where('start_time', '<=', $endTime)
                        ->where('end_time', '>=', $startTime);
                });
            });

        // Исключаем текущее бронирование при обновлении
        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        // Загружаем услуги для проверки
        $bookings = $query->with('services')->get();

        // Проверяем каждое бронирование на совпадение услуг
        foreach ($bookings as $booking) {
            $bookingServiceIds = $booking->services->pluck('id')->toArray();
            sort($bookingServiceIds);

            // Если наборы услуг совпадают — это дубликат
            if ($bookingServiceIds === $serviceIds) {
                return $booking;
            }
        }

        return null;
    }

    /**
     * Получить сообщение об ошибке для дублирующего бронирования
     *
     * @param Booking $duplicate
     * @return string
     */
    public function getErrorMessage(Booking $duplicate): string
    {
        $serviceNames = $duplicate->services->pluck('name')->join(', ');
        $startTime = $duplicate->start_time->format('d.m.Y H:i');
        $endTime = $duplicate->end_time->format('H:i');

        return sprintf(
            'Дублирующее бронирование уже существует: %s (%s - %s) с услугами: %s',
            $duplicate->client->name ?? 'Неизвестный клиент',
            $startTime,
            $endTime,
            $serviceNames
        );
    }

    /**
     * Найти все потенциальные дубликаты для клиента
     *
     * @param int $clientId
     * @param Carbon|null $from Начало периода поиска
     * @param Carbon|null $to Конец периода поиска
     * @return Collection
     */
    public function findPotentialDuplicates(
        int $clientId,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): Collection {
        $query = Booking::where('client_id', $clientId)
            ->with(['services', 'client']);

        if ($from) {
            $query->where('start_time', '>=', $from);
        }

        if ($to) {
            $query->where('end_time', '<=', $to);
        }

        $bookings = $query->orderBy('start_time')->get();

        $duplicates = collect();

        // Сравниваем каждое бронирование с остальными
        foreach ($bookings as $i => $booking1) {
            $services1 = $booking1->services->pluck('id')->sort()->values()->toArray();

            foreach ($bookings as $j => $booking2) {
                // Пропускаем сравнение с самим собой и уже обработанные пары
                if ($i >= $j) {
                    continue;
                }

                $services2 = $booking2->services->pluck('id')->sort()->values()->toArray();

                // Проверяем пересечение времени
                $timeOverlap = $booking1->start_time->lt($booking2->end_time) &&
                    $booking1->end_time->gt($booking2->start_time);

                // Проверяем совпадение услуг
                $servicesMatch = $services1 === $services2;

                if ($timeOverlap && $servicesMatch) {
                    $duplicates->push([
                        'booking1' => $booking1,
                        'booking2' => $booking2,
                        'overlap_start' => max($booking1->start_time, $booking2->start_time),
                        'overlap_end' => min($booking1->end_time, $booking2->end_time),
                    ]);
                }
            }
        }

        return $duplicates;
    }
}
