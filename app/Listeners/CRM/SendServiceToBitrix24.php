<?php

namespace App\Listeners\CRM;

use App\Events\Service\ServiceCreated;
use App\Events\Service\ServiceUpdated;
use App\Jobs\CRM\SyncProductToBitrix24Job;
use App\Models\TenantBitrix24Settings;
use Illuminate\Support\Facades\Log;

class SendServiceToBitrix24
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ServiceCreated|ServiceUpdated $event): void
    {
        $service = $event->service;

        // Получаем настройки интеграции
        $settings = TenantBitrix24Settings::where('tenant_id', $service->tenant_id)->first();

        // Проверяем, что интеграция включена
        if (!$settings || !$settings->enabled || !$settings->webhook_url) {
            Log::info('Bitrix24 integration disabled, skipping service sync', [
                'service_id' => $service->id,
            ]);
            return;
        }

        // Проверяем наличие catalog_iblock_id
        if (!$settings->catalog_iblock_id) {
            Log::info('Catalog iblock_id not configured, skipping service sync', [
                'service_id' => $service->id,
            ]);
            return;
        }

        // Отправляем Job на синхронизацию
        SyncProductToBitrix24Job::dispatch($service);

        Log::info('Service sync job dispatched', [
            'service_id' => $service->id,
            'event' => $event instanceof ServiceCreated ? 'created' : 'updated',
        ]);
    }
}
