<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotEmployee
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->isEmployee()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Доступ запрещён'], 403);
            }

            return redirect()->route('calendar')->with('error', 'Доступ запрещён');
        }

        return $next($request);
    }
}
