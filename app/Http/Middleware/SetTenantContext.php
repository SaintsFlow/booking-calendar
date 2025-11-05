<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Устанавливает контекст текущего тенанта из авторизованного пользователя
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            // Устанавливаем текущий тенант в конфигурацию
            config(['app.current_tenant_id' => $user->tenant_id]);

            // Можно также использовать app()->instance для доступа к тенанту
            if ($user->tenant) {
                app()->instance('current_tenant', $user->tenant);
            }
        }

        return $next($request);
    }
}
