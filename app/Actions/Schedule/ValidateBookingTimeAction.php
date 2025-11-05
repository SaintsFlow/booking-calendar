<?php

namespace App\Actions\Schedule;

use App\Models\User;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Валидация времени бронирования с учетом графика работы
 */
class ValidateBookingTimeAction
{
    public function __construct(
        private GetAvailableHoursAction $getAvailableHoursAction
    ) {}

    /**
     * Валидировать время бронирования
     *
     * @throws ValidationException
     */
    public function execute(
        User $employee,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId = null
    ): void {
        $startCarbon = Carbon::parse($startTime);
        $endCarbon = Carbon::parse($endTime);
        $date = $startCarbon->format('Y-m-d');

        // 1. Проверяем, что конец позже начала
        if ($endCarbon->lte($startCarbon)) {
            throw ValidationException::withMessages([
                'end_time' => 'Время окончания должно быть позже времени начала',
            ]);
        }

        // 2. Получаем рабочие часы сотрудника
        $workingHours = $this->getAvailableHoursAction->execute($employee, $date);

        if (!$workingHours) {
            throw ValidationException::withMessages([
                'start_time' => 'Сотрудник не работает в этот день',
            ]);
        }

        // 3. Проверяем, что время бронирования попадает в рабочие часы
        $workStart = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $workingHours['start']);
        $workEnd = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $workingHours['end']);

        if ($startCarbon->lt($workStart) || $endCarbon->gt($workEnd)) {
            throw ValidationException::withMessages([
                'start_time' => sprintf(
                    'Время бронирования должно быть в пределах рабочего времени: %s - %s',
                    $workingHours['start'],
                    $workingHours['end']
                ),
            ]);
        }

        // 4. Проверяем пересечение с существующими бронями
        $hasConflict = Booking::where('employee_id', $employee->id)
            ->when($excludeBookingId, function ($query) use ($excludeBookingId) {
                $query->where('id', '!=', $excludeBookingId);
            })
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where(function ($q) use ($startTime, $endTime) {
                    // Новая бронь начинается во время существующей
                    $q->where('start_time', '<=', $startTime)
                        ->where('end_time', '>', $startTime);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // Новая бронь заканчивается во время существующей
                    $q->where('start_time', '<', $endTime)
                        ->where('end_time', '>=', $endTime);
                })->orWhere(function ($q) use ($startTime, $endTime) {
                    // Новая бронь полностью охватывает существующую
                    $q->where('start_time', '>=', $startTime)
                        ->where('end_time', '<=', $endTime);
                });
            })
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'start_time' => 'На это время уже есть запись',
            ]);
        }
    }
}
