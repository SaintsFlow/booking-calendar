<?php

namespace App\Http\Controllers;

use App\Models\EmployeeVacation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VacationController extends Controller
{
    /**
     * Получить список отпусков
     */
    public function index(Request $request)
    {
        $query = EmployeeVacation::with(['employee', 'tenant'])
            ->forTenant(Auth::user()->tenant_id);

        // Фильтр по сотруднику
        if ($request->has('employee_id')) {
            $query->forEmployee($request->employee_id);
        }

        // Фильтр по дате
        if ($request->has('date')) {
            $query->activeOnDate($request->date);
        }

        // Фильтр по периоду
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->where(function ($q) use ($request) {
                $q->whereBetween('start_date', [$request->start_date, $request->end_date])
                    ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                    ->orWhere(function ($q2) use ($request) {
                        $q2->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                    });
            });
        }

        $vacations = $query->orderBy('start_date', 'desc')->get();

        return response()->json([
            'data' => $vacations,
            'count' => $vacations->count(),
        ]);
    }

    /**
     * Создать отпуск
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'required|string|in:vacation,sick_leave,day_off',
            'reason' => 'nullable|string|max:500',
        ]);

        $validated['tenant_id'] = Auth::user()->tenant_id;

        $vacation = EmployeeVacation::create($validated);

        return response()->json([
            'data' => $vacation->load(['employee', 'tenant']),
            'message' => 'Отпуск успешно создан',
        ], 201);
    }

    /**
     * Обновить отпуск
     */
    public function update(Request $request, EmployeeVacation $vacation)
    {
        // Проверка доступа к тенанту
        if ($vacation->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'type' => 'sometimes|required|string|in:vacation,sick_leave,day_off',
            'reason' => 'nullable|string|max:500',
        ]);

        $vacation->update($validated);

        return response()->json([
            'data' => $vacation->load(['employee', 'tenant']),
            'message' => 'Отпуск успешно обновлен',
        ]);
    }

    /**
     * Удалить отпуск
     */
    public function destroy(EmployeeVacation $vacation)
    {
        // Проверка доступа к тенанту
        if ($vacation->tenant_id !== Auth::user()->tenant_id) {
            abort(403);
        }

        $vacation->delete();

        return response()->json([
            'message' => 'Отпуск успешно удален',
        ]);
    }
}
