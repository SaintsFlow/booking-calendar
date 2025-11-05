<?php

namespace App\Http\Controllers;

use App\Models\Workplace;
use Illuminate\Http\Request;

class WorkplaceController extends Controller
{
    /**
     * Список мест работы
     */
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $workplaces = Workplace::query()
            ->forTenant($tenantId)
            ->withCount('employees')
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($workplaces);
    }

    /**
     * Создание места работы
     */
    public function store(Request $request)
    {
        $this->authorize('create', Workplace::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $workplace = Workplace::create([
            ...$validated,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return response()->json([
            'message' => 'Место работы успешно создано',
            'workplace' => $workplace,
        ], 201);
    }

    /**
     * Просмотр места работы
     */
    public function show(Workplace $workplace)
    {
        $workplace->load(['employees', 'services']);
        return response()->json($workplace);
    }

    /**
     * Обновление места работы
     */
    public function update(Request $request, Workplace $workplace)
    {
        $this->authorize('update', $workplace);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'working_hours' => 'nullable|array',
        ]);

        $workplace->update($validated);

        return response()->json([
            'message' => 'Место работы успешно обновлено',
            'workplace' => $workplace,
        ]);
    }

    /**
     * Удаление места работы
     */
    public function destroy(Workplace $workplace)
    {
        $this->authorize('delete', $workplace);

        $workplace->delete();

        return response()->json([
            'message' => 'Место работы успешно удалено',
        ]);
    }

    /**
     * Привязка сотрудников к месту работы
     */
    public function syncEmployees(Request $request, Workplace $workplace)
    {
        $this->authorize('update', $workplace);

        $validated = $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:users,id',
        ]);

        $workplace->employees()->sync($validated['employee_ids']);

        return response()->json([
            'message' => 'Сотрудники успешно привязаны',
            'workplace' => $workplace->load('employees'),
        ]);
    }
}
