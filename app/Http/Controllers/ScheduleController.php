<?php

namespace App\Http\Controllers;

use App\Actions\Schedule\GetAvailableHoursAction;
use App\Actions\Schedule\GetAvailableTimeSlotsAction;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    /**
     * Получить доступные часы работы сотрудника на дату
     */
    public function getAvailableHours(
        Request $request,
        GetAvailableHoursAction $action
    ) {
        $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
            'workplace_id' => 'nullable|integer|exists:workplaces,id',
        ]);

        $employee = User::findOrFail($request->employee_id);
        $workplace = $request->workplace_id
            ? Workplace::findOrFail($request->workplace_id)
            : null;

        $hours = $action->execute($employee, $request->date, $workplace);

        return response()->json([
            'working_hours' => $hours,
            'is_working' => $hours !== null,
        ]);
    }

    /**
     * Получить доступные временные слоты для бронирования
     */
    public function getAvailableTimeSlots(
        Request $request,
        GetAvailableTimeSlotsAction $action
    ) {
        $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
            'duration_minutes' => 'required|integer|min:15',
            'exclude_booking_id' => 'nullable|integer|exists:bookings,id',
        ]);

        $employee = User::findOrFail($request->employee_id);

        $slots = $action->execute(
            $employee,
            $request->date,
            $request->duration_minutes,
            $request->exclude_booking_id
        );

        return response()->json([
            'available_slots' => $slots,
            'count' => count($slots),
        ]);
    }

    /**
     * Получить график работы сотрудника на неделю
     */
    public function getWeeklySchedule(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
            'start_date' => 'required|date_format:Y-m-d',
        ]);

        $employee = User::with(['vacations', 'workplaces'])->findOrFail($request->employee_id);
        $startDate = \Carbon\Carbon::parse($request->start_date);

        $schedule = [];
        $action = app(GetAvailableHoursAction::class);

        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dateStr = $date->format('Y-m-d');

            $hours = $action->execute($employee, $dateStr);

            $schedule[] = [
                'date' => $dateStr,
                'day_of_week' => $date->englishDayOfWeek,
                'working_hours' => $hours,
                'is_working' => $hours !== null,
            ];
        }

        return response()->json([
            'employee_id' => $employee->id,
            'employee_name' => $employee->name,
            'schedule' => $schedule,
        ]);
    }
}
