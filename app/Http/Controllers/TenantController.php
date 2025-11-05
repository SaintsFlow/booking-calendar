<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    /**
     * Список всех тенантов (только для супер-админа)
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Tenant::class);

        $tenants = Tenant::query()
            ->withCount('users', 'bookings')
            ->when($request->status, fn($q, $status) => $q->where('subscription_status', $status))
            ->when($request->search, fn($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($tenants);
    }

    /**
     * Создание нового тенанта
     */
    public function store(Request $request)
    {
        $this->authorize('create', Tenant::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255|unique:tenants',
            'subscription_status' => ['nullable', Rule::in(['trial', 'active', 'blocked'])],
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',

            // Данные администратора кабинета
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'admin_password' => 'required|string|min:8',
        ]);

        // По умолчанию trial на 14 дней
        $trialEndsAt = $validated['trial_ends_at'] ?? now()->addDays(14);
        $subscriptionStatus = $validated['subscription_status'] ?? 'trial';

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'domain' => $validated['domain'] ?? null,
            'subscription_status' => $subscriptionStatus,
            'trial_ends_at' => $trialEndsAt,
            'subscription_ends_at' => $validated['subscription_ends_at'] ?? null,
        ]);

        // Создаём базовые статусы для тенанта
        $tenant->createDefaultStatuses();

        // Создаём администратора кабинета
        $admin = $tenant->users()->create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'role' => 'admin',
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Кабинет успешно создан',
            'tenant' => $tenant,
            'admin' => $admin,
        ], 201);
    }

    /**
     * Просмотр тенанта
     */
    public function show(Tenant $tenant)
    {
        $this->authorize('view', $tenant);

        $tenant->load([
            'users' => fn($q) => $q->orderBy('created_at', 'desc'),
            'workplaces',
            'statuses',
        ]);

        return response()->json($tenant);
    }

    /**
     * Обновление тенанта
     */
    public function update(Request $request, Tenant $tenant)
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'domain' => ['sometimes', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'subscription_status' => ['sometimes', Rule::in(['trial', 'active', 'blocked'])],
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',
            'settings' => 'nullable|array',
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => 'Тенант успешно обновлён',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Удаление тенанта
     */
    public function destroy(Tenant $tenant)
    {
        $this->authorize('delete', $tenant);

        $tenant->delete();

        return response()->json([
            'message' => 'Тенант успешно удалён',
        ]);
    }

    /**
     * Изменение статуса подписки
     */
    public function updateSubscription(Request $request, Tenant $tenant)
    {
        $this->authorize('manageSubscription', $tenant);

        $validated = $request->validate([
            'subscription_status' => ['required', Rule::in(['trial', 'active', 'blocked'])],
            'trial_ends_at' => 'nullable|date',
            'subscription_ends_at' => 'nullable|date',
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => 'Статус подписки обновлён',
            'tenant' => $tenant,
        ]);
    }

    /**
     * Войти в систему как администратор тенанта (impersonate)
     */
    public function impersonate(Request $request, Tenant $tenant)
    {
        $this->authorize('impersonate', $tenant);

        // Находим администратора этого тенанта
        $admin = $tenant->users()->where('role', 'admin')->first();

        if (!$admin) {
            return response()->json([
                'message' => 'У тенанта нет администратора',
            ], 422);
        }

        // Входим под администратором
        auth()->login($admin);

        return response()->json([
            'message' => 'Вход выполнен успешно',
            'user' => $admin->load('tenant'),
        ]);
    }
}
