<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantStatus
{
    /**
     * Проверяет статус подписки тенанта перед выполнением операций изменения
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Супер-админ может всё
        if ($user && $user->isSuperAdmin()) {
            return $next($request);
        }

        // Проверяем статус тенанта для всех операций изменения данных
        if ($user && $user->tenant) {
            $tenant = $user->tenant;

            // Если тенант заблокирован, запрещаем операции изменения
            if ($tenant->isBlocked()) {
                // Проверяем, является ли это операцией изменения (POST, PUT, PATCH, DELETE)
                if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                    return response()->json([
                        'message' => 'Ваш кабинет заблокирован. Операции изменения недоступны.',
                        'error' => 'TENANT_BLOCKED',
                    ], 403);
                }
            }

            // Можно добавить проверку истечения триала
            if ($tenant->isTrial() && $tenant->trial_ends_at && now()->isAfter($tenant->trial_ends_at)) {
                if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                    return response()->json([
                        'message' => 'Триальный период истёк. Пожалуйста, оформите подписку.',
                        'error' => 'TRIAL_EXPIRED',
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}
