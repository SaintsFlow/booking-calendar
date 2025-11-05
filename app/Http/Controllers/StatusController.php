<?php

namespace App\Http\Controllers;

use App\Models\Status;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    /**
     * Список статусов
     */
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $statuses = Status::query()
            ->forTenant($tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($statuses);
    }

    /**
     * Создание статуса
     */
    public function store(Request $request)
    {
        $this->authorize('create', Status::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'color' => 'required|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ]);

        // Если устанавливается новый дефолтный статус, сбрасываем у остальных
        if ($validated['is_default'] ?? false) {
            Status::forTenant($request->user()->tenant_id)
                ->update(['is_default' => false]);
        }

        $status = Status::create([
            ...$validated,
            'tenant_id' => $request->user()->tenant_id,
            'is_system' => false,
        ]);

        return response()->json([
            'message' => 'Статус успешно создан',
            'status' => $status,
        ], 201);
    }

    /**
     * Обновление статуса
     */
    public function update(Request $request, Status $status)
    {
        $this->authorize('update', $status);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'color' => 'sometimes|string|size:7|regex:/^#[0-9A-F]{6}$/i',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ]);

        // Системные статусы можно обновлять только частично
        if ($status->is_system && isset($validated['code'])) {
            unset($validated['code']);
        }

        // Если устанавливается новый дефолтный статус, сбрасываем у остальных
        if ($validated['is_default'] ?? false) {
            Status::forTenant($request->user()->tenant_id)
                ->where('id', '!=', $status->id)
                ->update(['is_default' => false]);
        }

        $status->update($validated);

        return response()->json([
            'message' => 'Статус успешно обновлён',
            'status' => $status,
        ]);
    }

    /**
     * Удаление статуса
     */
    public function destroy(Status $status)
    {
        $this->authorize('delete', $status);

        if (!$status->canDelete()) {
            return response()->json([
                'message' => 'Невозможно удалить статус',
                'reason' => $status->is_system
                    ? 'Системные статусы нельзя удалить'
                    : 'Есть бронирования с этим статусом',
            ], 422);
        }

        $status->delete();

        return response()->json([
            'message' => 'Статус успешно удалён',
        ]);
    }
}
