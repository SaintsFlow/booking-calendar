<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogBroadcastingRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('broadcasting/auth')) {
            Log::info('Broadcasting auth middleware', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'user_id' => $request->user()?->id,
                'has_session' => $request->hasSession(),
                'session_id' => $request->session()?->getId(),
                'cookies' => array_keys($request->cookies->all()),
                'input' => $request->all(),
            ]);
        }

        $response = $next($request);

        if ($request->is('broadcasting/auth')) {
            Log::info('Broadcasting auth response', [
                'status' => $response->getStatusCode(),
                'content_type' => $response->headers->get('Content-Type'),
                'content_length' => strlen($response->getContent()),
                'content_preview' => substr($response->getContent(), 0, 200),
            ]);
        }

        return $response;
    }
}
