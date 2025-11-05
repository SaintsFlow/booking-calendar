<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'tenant_id' => $request->user()->tenant_id,
                    'is_super_admin' => $request->user()->isSuperAdmin(),
                    'is_admin' => $request->user()->isAdmin(),
                    'is_manager' => $request->user()->isManager(),
                    'is_employee' => $request->user()->isEmployee(),
                    'has_manager_access' => $request->user()->hasManagerAccess(),
                    'has_admin_access' => $request->user()->hasAdminAccess(),
                ] : null,
                'tenant' => $request->user()?->tenant ? [
                    'id' => $request->user()->tenant->id,
                    'name' => $request->user()->tenant->name,
                    'subscription_status' => $request->user()->tenant->subscription_status,
                    'is_blocked' => $request->user()->tenant->isBlocked(),
                    'is_active' => $request->user()->tenant->isActive(),
                ] : null,
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'info' => fn() => $request->session()->get('info'),
            ],
        ];
    }
}
