<?php

namespace App\Http\Controllers;

use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

class CustomBroadcastController extends BroadcastController
{
    /**
     * Authenticate the request for channel access.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function authenticate(Request $request)
    {
        Log::info('Broadcasting auth request received', [
            'user_id' => $request->user()?->id,
            'channel' => $request->input('channel_name'),
            'socket_id' => $request->input('socket_id'),
            'is_authenticated' => Auth::check(),
            'has_session' => $request->hasSession(),
        ]);

        try {
            if ($request->hasSession()) {
                $request->session()->reflash();
            }

            Log::info('Calling Broadcast::auth()');
            $response = Broadcast::auth($request);

            if ($response === null) {
                Log::error('Broadcast::auth() returned null', [
                    'channel' => $request->input('channel_name'),
                ]);

                return response()->json([
                    'error' => 'Channel authorization not found',
                    'channel' => $request->input('channel_name'),
                ], 403);
            }

            Log::info('Broadcasting auth response', [
                'status' => $response->getStatusCode(),
                'content' => $response->getContent(),
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Broadcasting auth exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
