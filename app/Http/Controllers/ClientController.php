<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Список клиентов
     */
    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $clients = Client::query()
            ->forTenant($tenantId)
            ->when($request->search, fn($q, $search) => $q->search($search))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(50);

        return response()->json($clients);
    }

    /**
     * Создание клиента
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $client = Client::create([
            ...$validated,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return response()->json([
            'message' => 'Клиент успешно создан',
            'client' => $client,
        ], 201);
    }

    /**
     * Просмотр клиента
     */
    public function show(Client $client)
    {
        $this->authorize('view', $client);

        $client->load(['bookings' => fn($q) => $q->with(['status', 'employee', 'workplace'])->latest('start_time')->limit(10)]);

        return response()->json($client);
    }

    /**
     * Обновление клиента
     */
    public function update(Request $request, Client $client)
    {
        $this->authorize('update', $client);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $client->update($validated);

        return response()->json([
            'message' => 'Клиент успешно обновлён',
            'client' => $client,
        ]);
    }

    /**
     * Удаление клиента
     */
    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);

        // Проверяем, есть ли активные бронирования
        if ($client->bookings()->whereHas('status', fn($q) => $q->where('code', '!=', 'cancelled_by_admin'))->exists()) {
            return response()->json([
                'message' => 'Невозможно удалить клиента с активными бронированиями',
            ], 422);
        }

        $client->delete();

        return response()->json([
            'message' => 'Клиент успешно удалён',
        ]);
    }
}
