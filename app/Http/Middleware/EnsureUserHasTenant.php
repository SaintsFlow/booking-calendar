<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasTenant
{
    /**
     * Проверяет, что у пользователя есть тенант (кроме супер-админа)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Супер-админ не привязан к тенанту
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Остальные пользователи должны иметь тенант
        if (!$user->tenant_id || !$user->tenant) {
            return response()->json([
                'message' => 'У пользователя не установлен кабинет.',
                'error' => 'NO_TENANT',
            ], 403);
        }

        return $next($request);
    }
}
