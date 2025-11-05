<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Список пользователей
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query();

        // Супер-админ видит всех, остальные - только своего тенанта
        if (!$request->user()->isSuperAdmin()) {
            $query->forTenant($request->user()->tenant_id);
        }

        $users = $query
            ->with('workplaces')
            ->when($request->role, fn($q, $role) => $q->where('role', $role))
            ->when($request->search, fn($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(50);

        return response()->json($users);
    }

    /**
     * Создание пользователя
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['admin', 'manager', 'employee'])],
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'workplace_ids' => 'nullable|array',
            'workplace_ids.*' => 'exists:workplaces,id',
            'bitrix24_user_id' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'bitrix24_user_id' => $validated['bitrix24_user_id'] ?? null,
        ]);

        // Привязываем места работы для сотрудников
        if (isset($validated['workplace_ids']) && $user->isEmployee()) {
            $user->workplaces()->sync($validated['workplace_ids']);
        }

        $user->load('workplaces');

        return response()->json([
            'message' => 'Пользователь успешно создан',
            'user' => $user,
        ], 201);
    }

    /**
     * Просмотр пользователя
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        $user->load(['workplaces', 'tenant']);

        return response()->json($user);
    }

    /**
     * Обновление пользователя
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'workplace_ids' => 'nullable|array',
            'workplace_ids.*' => 'exists:workplaces,id',
            'working_hours' => 'nullable|array',
            'custom_schedules' => 'nullable|array',
            'bitrix24_user_id' => 'nullable|string|max:255',
        ]);

        // Обновляем основные данные
        $updateData = collect($validated)->except(['password', 'workplace_ids'])->toArray();

        if (isset($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Обновляем места работы для сотрудников
        if (isset($validated['workplace_ids']) && $user->isEmployee()) {
            $user->workplaces()->sync($validated['workplace_ids']);
        }

        $user->load('workplaces');

        return response()->json([
            'message' => 'Пользователь успешно обновлён',
            'user' => $user,
        ]);
    }

    /**
     * Удаление пользователя
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json([
            'message' => 'Пользователь успешно удалён',
        ]);
    }

    /**
     * Изменение роли пользователя
     */
    public function updateRole(Request $request, User $user)
    {
        $this->authorize('manageRoles', $user);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'manager', 'employee'])],
        ]);

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'message' => 'Роль пользователя успешно изменена',
            'user' => $user,
        ]);
    }
}
