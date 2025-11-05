<?php

namespace App\Actions\Schedule;

use App\Models\User;
use App\Models\Booking;
use Carbon\Carbon;

/**
 * Получение доступных временных слотов для бронирования
 */
class GetAvailableTimeSlotsAction
{
    public function __construct(
        private GetAvailableHoursAction $getAvailableHoursAction
    ) {}

    /**
     * Получить доступные временные слоты для сотрудника
     *
     * @param User $employee
     * @param string $date Дата в формате Y-m-d
     * @param int $durationMinutes Длительность услуги в минутах
     * @param int|null $excludeBookingId ID брони, которую нужно исключить (при редактировании)
     * @return array ['09:00', '09:30', '10:00', ...]
     */
    public function execute(
        User $employee,
        string $date,
        int $durationMinutes = 60,
        ?int $excludeBookingId = null
    ): array {
        // Получаем рабочие часы сотрудника
        $workingHours = $this->getAvailableHoursAction->execute($employee, $date);

        if (!$workingHours || !isset($workingHours['start'], $workingHours['end'])) {
            return []; // Сотрудник не работает в этот день
        }

        // Получаем существующие бронирования
        $existingBookings = $this->getExistingBookings($employee->id, $date, $excludeBookingId);

        // Генерируем все возможные слоты
        $allSlots = $this->generateTimeSlots(
            $workingHours['start'],
            $workingHours['end'],
            15 // Интервал в минутах
        );

        // Фильтруем занятые слоты
        return $this->filterAvailableSlots($allSlots, $existingBookings, $durationMinutes);
    }

    /**
     * Получить существующие бронирования сотрудника на дату
     */
    private function getExistingBookings(int $employeeId, string $date, ?int $excludeBookingId): array
    {
        $bookings = Booking::where('employee_id', $employeeId)
            ->whereDate('start_time', $date)
            ->when($excludeBookingId, function ($query) use ($excludeBookingId) {
                $query->where('id', '!=', $excludeBookingId);
            })
            ->orderBy('start_time')
            ->get(['start_time', 'end_time']);

        return $bookings->map(function ($booking) {
            return [
                'start' => Carbon::parse($booking->start_time)->format('H:i'),
                'end' => Carbon::parse($booking->end_time)->format('H:i'),
            ];
        })->toArray();
    }

    /**
     * Генерировать временные слоты
     */
    private function generateTimeSlots(string $startTime, string $endTime, int $intervalMinutes): array
    {
        $slots = [];
        $current = Carbon::createFromFormat('H:i', $startTime);
        $end = Carbon::createFromFormat('H:i', $endTime);

        while ($current->lt($end)) {
            $slots[] = $current->format('H:i');
            $current->addMinutes($intervalMinutes);
        }

        return $slots;
    }

    /**
     * Фильтровать доступные слоты (убрать занятые)
     */
    private function filterAvailableSlots(array $allSlots, array $existingBookings, int $durationMinutes): array
    {
        $availableSlots = [];

        foreach ($allSlots as $slot) {
            if ($this->isSlotAvailable($slot, $existingBookings, $durationMinutes)) {
                $availableSlots[] = $slot;
            }
        }

        return $availableSlots;
    }

    /**
     * Проверить, доступен ли слот
     */
    private function isSlotAvailable(string $slotTime, array $existingBookings, int $durationMinutes): bool
    {
        $slotStart = Carbon::createFromFormat('H:i', $slotTime);
        $slotEnd = $slotStart->copy()->addMinutes($durationMinutes);

        foreach ($existingBookings as $booking) {
            $bookingStart = Carbon::createFromFormat('H:i', $booking['start']);
            $bookingEnd = Carbon::createFromFormat('H:i', $booking['end']);

            // Проверяем пересечение
            if ($slotStart->lt($bookingEnd) && $slotEnd->gt($bookingStart)) {
                return false;
            }
        }

        return true;
    }
}
