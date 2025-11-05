<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAdminOrManager
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, ['admin', 'manager'])) {
            return response()->json([
                'message' => 'Доступ запрещен. Только для администраторов и менеджеров.'
            ], 403);
        }

        return $next($request);
    }
}
