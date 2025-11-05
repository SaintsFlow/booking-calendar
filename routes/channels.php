<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Авторизация для канала кабинета (tenant)
// Пользователь может подключиться только к каналу своего кабинета
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    Log::info('Broadcasting auth attempt', [
        'user_id' => $user?->id,
        'user_tenant' => $user?->tenant_id,
        'requested_tenant' => $tenantId,
        'user_exists' => $user !== null,
    ]);

    if (!$user) {
        Log::error('Broadcasting auth failed: User is null');
        return false;
    }

    if ((int) $user->tenant_id === (int) $tenantId) {
        Log::info('Broadcasting auth success', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
        ]);
        return true;
    }

    Log::warning('Broadcasting auth failed: Tenant mismatch', [
        'user_tenant' => $user->tenant_id,
        'requested_tenant' => $tenantId,
    ]);
    return false;
});


// Авторизация для канала кабинета (tenant)
// Пользователь может подключиться только к каналу своего кабинета
// Примечание: Laravel Echo автоматически добавляет префикс "private-" к имени канала
Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    Log::info('Broadcasting auth attempt', [
        'user_id' => $user?->id,
        'user_tenant' => $user?->tenant_id,
        'requested_tenant' => $tenantId,
        'user_exists' => $user !== null,
    ]);

    if (!$user) {
        Log::error('Broadcasting auth failed: User is null');
        return false;
    }

    if ((int) $user->tenant_id === (int) $tenantId) {
        Log::info('Broadcasting auth success', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
        ]);
        return true;
    }

    Log::warning('Broadcasting auth failed: Tenant mismatch', [
        'user_tenant' => $user->tenant_id,
        'requested_tenant' => $tenantId,
    ]);
    return false;
});
