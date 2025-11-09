<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /**
     * Показать список всех подписок (только для супер-админа)
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Subscription::class);

        $subscriptions = Subscription::with('tenant')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Admin/Subscriptions', [
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Показать форму создания подписки
     */
    public function create(): Response
    {
        $this->authorize('create', Subscription::class);

        $tenants = Tenant::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/SubscriptionCreate', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Создать новую подписку
     */
    public function store(Request $request)
    {
        $this->authorize('create', Subscription::class);

        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_name' => 'required|string|max:255',
            'status' => 'required|in:active,paused,cancelled,expired',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'trial_ends_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $subscription = Subscription::create($validated);

        return redirect()->route('admin.subscriptions.index')
            ->with('success', 'Подписка успешно создана');
    }

    /**
     * Показать детали подписки
     */
    public function show(Subscription $subscription): Response
    {
        $this->authorize('view', $subscription);

        $subscription->load('tenant');

        return Inertia::render('Admin/SubscriptionShow', [
            'subscription' => $subscription,
        ]);
    }

    /**
     * Показать форму редактирования подписки
     */
    public function edit(Subscription $subscription): Response
    {
        $this->authorize('update', $subscription);

        $subscription->load('tenant');
        $tenants = Tenant::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/SubscriptionEdit', [
            'subscription' => $subscription,
            'tenants' => $tenants,
        ]);
    }

    /**
     * Обновить подписку
     */
    public function update(Request $request, Subscription $subscription)
    {
        $this->authorize('update', $subscription);

        $validated = $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'plan_name' => 'required|string|max:255',
            'status' => 'required|in:active,paused,cancelled,expired',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'trial_ends_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $subscription->update($validated);

        return redirect()->route('admin.subscriptions.index')
            ->with('success', 'Подписка успешно обновлена');
    }

    /**
     * Удалить подписку
     */
    public function destroy(Subscription $subscription)
    {
        $this->authorize('delete', $subscription);

        $subscription->delete();

        return redirect()->route('admin.subscriptions.index')
            ->with('success', 'Подписка успешно удалена');
    }

    /**
     * Приостановить подписку
     */
    public function pause(Subscription $subscription)
    {
        $this->authorize('pause', $subscription);

        $subscription->update(['status' => 'paused']);

        return back()->with('success', 'Подписка приостановлена');
    }

    /**
     * Возобновить подписку
     */
    public function resume(Subscription $subscription)
    {
        $this->authorize('resume', $subscription);

        $subscription->update(['status' => 'active']);

        return back()->with('success', 'Подписка возобновлена');
    }

    /**
     * Отменить подписку
     */
    public function cancel(Subscription $subscription)
    {
        $this->authorize('cancel', $subscription);

        $subscription->update(['status' => 'cancelled']);

        return back()->with('success', 'Подписка отменена');
    }
}
