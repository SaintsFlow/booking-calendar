<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /**
     * Получение данных календаря
     * 
     * Поддерживает режимы: day, week, month
     * Фильтры: workplace_id, employee_id, status_id
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'view' => 'required|in:day,week,month',
            'date' => 'required|date',
            'workplace_id' => 'nullable|exists:workplaces,id',
            'employee_id' => 'nullable|exists:users,id',
            'status_id' => 'nullable|exists:statuses,id',
            'show_cancelled' => 'nullable|boolean',
        ]);

        $tenantId = $request->user()->tenant_id;

        // Супер-админы не имеют доступа к календарю (нет tenant)
        if (!$tenantId) {
            return response()->json([
                'view' => $validated['view'],
                'start_date' => now()->toISOString(),
                'end_date' => now()->toISOString(),
                'calendar' => [],
                'workplaces' => [],
                'employees' => [],
                'filter_dictionary' => [
                    'workplaces' => [],
                    'employees' => [],
                    'statuses' => [],
                ],
            ]);
        }

        $view = $validated['view'];
        $date = Carbon::parse($validated['date']);

        // Определяем диапазон дат в зависимости от режима
        [$startDate, $endDate] = $this->getDateRange($view, $date);

        // Строим запрос бронирований
        $bookingsQuery = Booking::query()
            ->forTenant($tenantId)
            ->with([
                'employee:id,name,email',
                'employee.workplaces:workplaces.id,workplaces.name',
                'client:id,first_name,last_name,phone',
                'workplace:id,name',
                'status:id,name,code,color',
                'services:id,name,duration_minutes,price',
            ])
            ->forPeriod($startDate, $endDate);

        // Применяем фильтры
        if ($validated['workplace_id'] ?? null) {
            $bookingsQuery->forWorkplace($validated['workplace_id']);
        }

        if ($validated['employee_id'] ?? null) {
            $bookingsQuery->forEmployee($validated['employee_id']);
        }

        if ($validated['status_id'] ?? null) {
            $bookingsQuery->forStatus($validated['status_id']);
        }

        // Фильтр отменённых бронирований
        // Сотрудники НИКОГДА не видят отменённые, даже если параметр передан
        $showCancelled = ($validated['show_cancelled'] ?? false) && !$request->user()->isEmployee();
        if (!$showCancelled) {
            $bookingsQuery->excludeCancelled();
        }

        // Для сотрудников показываем только их записи
        if ($request->user()->isEmployee()) {
            $bookingsQuery->forEmployee($request->user()->id);
        }

        $bookings = $bookingsQuery->orderBy('start_time')->get();

        // Определяем какие сотрудники и места работы показывать
        // Если фильтры не применены - показываем всех, иначе только тех, у кого есть брони
        $hasFilters = ($validated['workplace_id'] ?? null) ||
            ($validated['employee_id'] ?? null) ||
            ($validated['status_id'] ?? null) ||
            $request->user()->isEmployee(); // Для сотрудников всегда считаем что фильтр применён

        if ($hasFilters) {
            // С фильтрами: показываем только тех, у кого есть бронирования
            $employeeIds = $bookings->pluck('employee_id')->unique();
            $workplaceIds = $bookings->pluck('workplace_id')->unique()->filter();
        } else {
            // Без фильтров: показываем всех активных
            $employeeIds = \App\Models\User::forTenant($tenantId)
                ->where('is_active', true)
                ->whereIn('role', ['admin', 'manager', 'employee'])
                ->pluck('id');
            $workplaceIds = \App\Models\Workplace::forTenant($tenantId)
                ->pluck('id');
        }

        // Загружаем места работы и сотрудников
        $workplaces = \App\Models\Workplace::whereIn('id', $workplaceIds)
            ->forTenant($tenantId)
            ->orderBy('name')
            ->get();

        $employees = \App\Models\User::whereIn('id', $employeeIds)
            ->forTenant($tenantId)
            ->with('workplaces:workplaces.id,workplaces.name')
            ->orderBy('name')
            ->get();

        // Группируем бронирования по сотрудникам и датам
        $calendar = $this->formatCalendarData($bookings, $view, $startDate, $endDate);

        // Получаем словарь доступных значений фильтров
        $filterDictionary = $this->getFilterDictionary($tenantId, $validated);

        return response()->json([
            'view' => $view,
            'start_date' => $startDate->toISOString(),
            'end_date' => $endDate->toISOString(),
            'calendar' => $calendar,
            'workplaces' => $workplaces,
            'employees' => $employees,
            'filter_dictionary' => $filterDictionary,
        ]);
    }

    /**
     * Получение словаря доступных значений фильтров на основе текущих фильтров
     */
    private function getFilterDictionary(int $tenantId, array $currentFilters): array
    {
        // Базовый запрос бронирований
        $baseQuery = Booking::query()->forTenant($tenantId);

        // Получаем все места работы (для начального состояния)
        $allWorkplaces = \App\Models\Workplace::forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Получаем все статусы (для начального состояния)
        $allStatuses = \App\Models\Status::forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'color']);

        // Если выбрано место работы - фильтруем сотрудников и статусы
        if ($currentFilters['workplace_id'] ?? null) {
            $workplaceBookings = (clone $baseQuery)
                ->forWorkplace($currentFilters['workplace_id'])
                ->get();

            $availableEmployeeIds = $workplaceBookings->pluck('employee_id')->unique();
            $availableStatusIds = $workplaceBookings->pluck('status_id')->unique();

            $availableEmployees = \App\Models\User::whereIn('id', $availableEmployeeIds)
                ->forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

            $availableStatuses = \App\Models\Status::whereIn('id', $availableStatusIds)
                ->forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'color']);

            return [
                'workplaces' => $allWorkplaces,
                'employees' => $availableEmployees,
                'statuses' => $availableStatuses,
            ];
        }

        // Если выбран сотрудник - фильтруем места работы и статусы
        if ($currentFilters['employee_id'] ?? null) {
            $employeeBookings = (clone $baseQuery)
                ->forEmployee($currentFilters['employee_id'])
                ->get();

            $availableWorkplaceIds = $employeeBookings->pluck('workplace_id')->unique()->filter();
            $availableStatusIds = $employeeBookings->pluck('status_id')->unique();

            $availableWorkplaces = \App\Models\Workplace::whereIn('id', $availableWorkplaceIds)
                ->forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name']);

            $availableStatuses = \App\Models\Status::whereIn('id', $availableStatusIds)
                ->forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'color']);

            $allEmployees = \App\Models\User::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

            return [
                'workplaces' => $availableWorkplaces,
                'employees' => $allEmployees,
                'statuses' => $availableStatuses,
            ];
        }

        // Если выбран статус - фильтруем места работы и сотрудников
        if ($currentFilters['status_id'] ?? null) {
            $statusBookings = (clone $baseQuery)
                ->forStatus($currentFilters['status_id'])
                ->get();

            $availableWorkplaceIds = $statusBookings->pluck('workplace_id')->unique()->filter();
            $availableEmployeeIds = $statusBookings->pluck('employee_id')->unique();

            $availableWorkplaces = \App\Models\Workplace::whereIn('id', $availableWorkplaceIds)
                ->forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name']);

            $availableEmployees = \App\Models\User::whereIn('id', $availableEmployeeIds)
                ->forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

            return [
                'workplaces' => $availableWorkplaces,
                'employees' => $availableEmployees,
                'statuses' => $allStatuses,
            ];
        }

        // Если ничего не выбрано - возвращаем все доступные значения
        $allEmployees = \App\Models\User::forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return [
            'workplaces' => $allWorkplaces,
            'employees' => $allEmployees,
            'statuses' => $allStatuses,
        ];
    }

    /**
     * Определение диапазона дат в зависимости от режима просмотра
     */
    private function getDateRange(string $view, Carbon $date): array
    {
        return match ($view) {
            'day' => [
                $date->copy()->startOfDay(),
                $date->copy()->endOfDay(),
            ],
            'week' => [
                $date->copy()->startOfWeek(),
                $date->copy()->endOfWeek(),
            ],
            'month' => [
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth(),
            ],
        };
    }

    /**
     * Форматирование данных календаря
     */
    private function formatCalendarData($bookings, string $view, Carbon $startDate, Carbon $endDate)
    {
        // Группируем по сотрудникам
        $employeeBookings = $bookings->groupBy('employee_id');

        $calendar = [];

        foreach ($employeeBookings as $employeeId => $employeeBookingsList) {
            $employee = $employeeBookingsList->first()->employee;

            $employeeData = [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name,
                'bookings' => [],
            ];

            // Для режима "день" группируем по часам
            if ($view === 'day') {
                $employeeData['bookings'] = $this->formatDayBookings($employeeBookingsList, $startDate);
            }
            // Для режима "неделя" группируем по дням
            elseif ($view === 'week') {
                $employeeData['bookings'] = $this->formatWeekBookings($employeeBookingsList, $startDate, $endDate);
            }
            // Для режима "месяц" группируем по дням
            else {
                $employeeData['bookings'] = $this->formatMonthBookings($employeeBookingsList, $startDate, $endDate);
            }

            $calendar[] = $employeeData;
        }

        return $calendar;
    }

    /**
     * Форматирование бронирований для режима "день" (по часам)
     */
    private function formatDayBookings($bookings, Carbon $date)
    {
        $hours = [];

        // Создаём слоты по часам (например, с 8:00 до 20:00)
        for ($hour = 8; $hour < 20; $hour++) {
            $hourStart = $date->copy()->setTime($hour, 0);
            $hourEnd = $hourStart->copy()->addHour();

            $hourBookings = $bookings->filter(function ($booking) use ($hourStart, $hourEnd) {
                return $booking->start_time >= $hourStart && $booking->start_time < $hourEnd;
            })->values()->map(function ($booking) {
                return $this->formatBooking($booking);
            });

            $hours[] = [
                'hour' => $hour,
                'time' => $hourStart->format('H:i'),
                'bookings' => $hourBookings,
            ];
        }

        return $hours;
    }

    /**
     * Форматирование бронирований для режима "неделя" (по дням)
     */
    private function formatWeekBookings($bookings, Carbon $startDate, Carbon $endDate)
    {
        $days = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();

            $dayBookings = $bookings->filter(function ($booking) use ($dayStart, $dayEnd) {
                return $booking->start_time >= $dayStart && $booking->start_time <= $dayEnd;
            })->values()->map(function ($booking) {
                return $this->formatBooking($booking);
            });

            $days[] = [
                'date' => $currentDate->toDateString(),
                'day_name' => $currentDate->locale('ru')->dayName,
                'bookings' => $dayBookings,
            ];

            $currentDate->addDay();
        }

        return $days;
    }

    /**
     * Форматирование бронирований для режима "месяц" (по дням)
     */
    private function formatMonthBookings($bookings, Carbon $startDate, Carbon $endDate)
    {
        return $this->formatWeekBookings($bookings, $startDate, $endDate);
    }

    /**
     * Форматирование одного бронирования для ответа
     */
    private function formatBooking(Booking $booking): array
    {
        return [
            'id' => $booking->id,
            'employee_id' => $booking->employee_id,
            'start_time' => $booking->start_time->toISOString(),
            'end_time' => $booking->end_time->toISOString(),
            'duration_minutes' => $booking->duration_minutes,
            'client' => [
                'id' => $booking->client->id,
                'name' => $booking->client->full_name,
                'phone' => $booking->client->phone,
            ],
            'employee' => [
                'id' => $booking->employee->id,
                'name' => $booking->employee->name,
            ],
            'workplace' => [
                'id' => $booking->workplace->id,
                'name' => $booking->workplace->name,
            ],
            'status' => [
                'id' => $booking->status->id,
                'name' => $booking->status->name,
                'code' => $booking->status->code,
                'color' => $booking->status->color,
            ],
            'services' => $booking->services->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'duration' => $s->duration_minutes,
                'price' => $s->price,
            ]),
            'total_price' => $booking->total_price,
            'comment' => $booking->comment,
            'client_attended' => $booking->client_attended,
        ];
    }

    /**
     * Получение доступных временных слотов для сотрудника на дату
     */
    public function getAvailableSlots(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'workplace_id' => 'required|exists:workplaces,id',
            'date' => 'required|date',
            'duration_minutes' => 'required|integer|min:1',
            'exclude_booking_id' => 'nullable|exists:bookings,id', // Для редактирования
        ]);

        $tenantId = $request->user()->tenant_id;
        $date = Carbon::parse($validated['date']);
        $startDate = $date->copy()->startOfDay();
        $endDate = $date->copy()->endOfDay();

        // Получаем все бронирования сотрудника на эту дату
        $bookingsQuery = Booking::query()
            ->forTenant($tenantId)
            ->forEmployee($validated['employee_id'])
            ->forWorkplace($validated['workplace_id'])
            ->forPeriod($startDate, $endDate)
            ->orderBy('start_time');

        // Исключаем текущее бронирование при редактировании
        if ($validated['exclude_booking_id'] ?? null) {
            $bookingsQuery->where('id', '!=', $validated['exclude_booking_id']);
        }

        $existingBookings = $bookingsQuery->get();

        // Рабочие часы (можно сделать настраиваемыми)
        $workStart = $date->copy()->setTime(8, 0);
        $workEnd = $date->copy()->setTime(20, 0);

        // Генерируем занятые слоты
        $occupiedSlots = $existingBookings->map(function ($booking) {
            return [
                'start' => $booking->start_time->format('H:i'),
                'end' => $booking->end_time->format('H:i'),
                'start_minutes' => $booking->start_time->hour * 60 + $booking->start_time->minute,
                'end_minutes' => $booking->end_time->hour * 60 + $booking->end_time->minute,
            ];
        })->toArray();

        // Находим первый доступный слот
        $durationMinutes = $validated['duration_minutes'];
        $firstAvailableSlot = $this->findFirstAvailableSlot($workStart, $workEnd, $occupiedSlots, $durationMinutes);

        return response()->json([
            'occupied_slots' => $occupiedSlots,
            'first_available_slot' => $firstAvailableSlot,
            'work_hours' => [
                'start' => $workStart->format('H:i'),
                'end' => $workEnd->format('H:i'),
            ],
        ]);
    }

    /**
     * Проверка конфликта времени
     */
    public function checkTimeConflict(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'workplace_id' => 'required|exists:workplaces,id',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer|min:1',
            'exclude_booking_id' => 'nullable|exists:bookings,id',
        ]);

        $tenantId = $request->user()->tenant_id;
        $dateTime = Carbon::parse($validated['date'] . ' ' . $validated['time']);
        $endTime = $dateTime->copy()->addMinutes($validated['duration_minutes']);

        // Проверяем существующие бронирования
        $conflictQuery = Booking::query()
            ->forTenant($tenantId)
            ->forEmployee($validated['employee_id'])
            ->forWorkplace($validated['workplace_id'])
            ->where(function ($q) use ($dateTime, $endTime) {
                // Конфликт если:
                // 1. Новое начинается во время существующего
                // 2. Новое заканчивается во время существующего
                // 3. Новое полностью покрывает существующее
                $q->where(function ($q2) use ($dateTime, $endTime) {
                    $q2->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $dateTime);
                });
            });

        if ($validated['exclude_booking_id'] ?? null) {
            $conflictQuery->where('id', '!=', $validated['exclude_booking_id']);
        }

        $conflict = $conflictQuery->first();

        if ($conflict) {
            return response()->json([
                'has_conflict' => true,
                'conflict' => [
                    'id' => $conflict->id,
                    'start_time' => $conflict->start_time->format('H:i'),
                    'end_time' => $conflict->end_time->format('H:i'),
                    'client_name' => $conflict->client->full_name,
                ],
                'message' => 'Это время уже занято',
            ]);
        }

        return response()->json([
            'has_conflict' => false,
            'message' => 'Время свободно',
        ]);
    }

    /**
     * Найти первый доступный слот
     */
    private function findFirstAvailableSlot(Carbon $workStart, Carbon $workEnd, array $occupiedSlots, int $durationMinutes)
    {
        $currentTime = $workStart->copy();
        $workEndMinutes = $workEnd->hour * 60 + $workEnd->minute;

        while ($currentTime < $workEnd) {
            $currentMinutes = $currentTime->hour * 60 + $currentTime->minute;
            $requiredEndMinutes = $currentMinutes + $durationMinutes;

            // Проверяем, не выходит ли за рабочие часы
            if ($requiredEndMinutes > $workEndMinutes) {
                break;
            }

            // Проверяем конфликты с существующими бронированиями
            $hasConflict = false;
            foreach ($occupiedSlots as $slot) {
                if ($currentMinutes < $slot['end_minutes'] && $requiredEndMinutes > $slot['start_minutes']) {
                    $hasConflict = true;
                    // Перепрыгиваем на конец занятого слота
                    $currentTime = Carbon::parse($currentTime->format('Y-m-d'))->addMinutes($slot['end_minutes']);
                    break;
                }
            }

            if (!$hasConflict) {
                return $currentTime->format('H:i');
            }
        }

        return null; // Нет доступных слотов
    }
}
