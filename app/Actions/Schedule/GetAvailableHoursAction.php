<?php

namespace App\Actions\Schedule;

use App\Models\User;
use App\Models\Workplace;
use App\Models\EmployeeVacation;
use Carbon\Carbon;

/**
 * Получение доступных часов работы для сотрудника на конкретную дату
 */
class GetAvailableHoursAction
{
    /**
     * Получить доступные часы для сотрудника
     *
     * @param User $employee
     * @param string $date Дата в формате Y-m-d
     * @param Workplace|null $workplace
     * @return array|null ['start' => '09:00', 'end' => '18:00', 'is_working' => true] или null если не работает
     */
    public function execute(User $employee, string $date, ?Workplace $workplace = null): ?array
    {
        $carbonDate = Carbon::parse($date);
        $dayOfWeek = strtolower($carbonDate->englishDayOfWeek); // monday, tuesday, ...

        // 1. Проверяем отпуск/больничный
        if ($this->isEmployeeOnVacation($employee, $date)) {
            return null;
        }

        // 2. Проверяем особый график на конкретную дату
        $customSchedule = $this->getCustomScheduleForDate($employee, $date);
        if ($customSchedule !== null) {
            return $customSchedule;
        }

        // 3. Проверяем персональный график сотрудника
        if ($employee->working_hours && isset($employee->working_hours[$dayOfWeek])) {
            $hours = $employee->working_hours[$dayOfWeek];
            if (isset($hours['is_working']) && !$hours['is_working']) {
                return null;
            }
            return $hours;
        }

        // 4. Используем график места работы
        if ($workplace && $workplace->working_hours && isset($workplace->working_hours[$dayOfWeek])) {
            $hours = $workplace->working_hours[$dayOfWeek];
            if (isset($hours['is_working']) && !$hours['is_working']) {
                return null;
            }
            return $hours;
        }

        // 5. График по умолчанию (если ничего не настроено)
        return $this->getDefaultWorkingHours($dayOfWeek);
    }

    /**
     * Проверить, находится ли сотрудник в отпуске
     */
    private function isEmployeeOnVacation(User $employee, string $date): bool
    {
        return EmployeeVacation::forEmployee($employee->id)
            ->activeOnDate($date)
            ->exists();
    }

    /**
     * Получить особый график на конкретную дату
     */
    private function getCustomScheduleForDate(User $employee, string $date): ?array
    {
        if (!$employee->custom_schedules) {
            return null;
        }

        foreach ($employee->custom_schedules as $schedule) {
            if (isset($schedule['date']) && $schedule['date'] === $date) {
                return [
                    'start' => $schedule['start'] ?? null,
                    'end' => $schedule['end'] ?? null,
                    'is_working' => $schedule['is_working'] ?? true,
                ];
            }
        }

        return null;
    }

    /**
     * График работы по умолчанию
     */
    private function getDefaultWorkingHours(string $dayOfWeek): ?array
    {
        // Выходные по умолчанию
        if (in_array($dayOfWeek, ['saturday', 'sunday'])) {
            return null;
        }

        // Рабочие дни: 9:00 - 18:00
        return [
            'start' => '09:00',
            'end' => '18:00',
            'is_working' => true,
        ];
    }
}
