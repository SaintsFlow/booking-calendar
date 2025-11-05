<?php

namespace App\Http\Controllers;

use App\Events\Service\ServiceCreated;
use App\Events\Service\ServiceDeleted;
use App\Events\Service\ServiceUpdated;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Список услуг
     */
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $services = Service::query()
            ->forTenant($tenantId)
            ->with('workplace')
            ->when($request->workplace_id, fn($q, $id) => $q->forWorkplace($id))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->get();

        return response()->json($services);
    }

    /**
     * Создание услуги
     */
    public function store(Request $request)
    {
        $this->authorize('create', Service::class);

        $validated = $request->validate([
            'workplace_id' => 'nullable|exists:workplaces,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $service = Service::create([
            ...$validated,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        // Отправляем событие в CRM
        event(new ServiceCreated($service));

        return response()->json([
            'message' => 'Услуга успешно создана',
            'service' => $service,
        ], 201);
    }

    /**
     * Просмотр услуги
     */
    public function show(Service $service)
    {
        $service->load('workplace');
        return response()->json($service);
    }

    /**
     * Обновление услуги
     */
    public function update(Request $request, Service $service)
    {
        $this->authorize('update', $service);

        $validated = $request->validate([
            'workplace_id' => 'nullable|exists:workplaces,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $service->update($validated);

        // Отправляем событие в CRM
        event(new ServiceUpdated($service));

        return response()->json([
            'message' => 'Услуга успешно обновлена',
            'service' => $service,
        ]);
    }

    /**
     * Удаление услуги
     */
    public function destroy(Service $service)
    {
        $this->authorize('delete', $service);

        // Отправляем событие в CRM перед удалением
        event(new ServiceDeleted($service));

        $service->delete();

        return response()->json([
            'message' => 'Услуга успешно удалена',
        ]);
    }
}
