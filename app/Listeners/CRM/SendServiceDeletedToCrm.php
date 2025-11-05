<?php

namespace App\Listeners\CRM;

use App\Events\Service\ServiceDeleted;
use App\Jobs\CRM\SendEventToCrmJob;
use Illuminate\Support\Facades\Log;

class SendServiceDeletedToCrm
{
    public function handle(ServiceDeleted $event): void
    {
        Log::info('🎧 Listener: Отправка ServiceDeleted в CRM', [
            'service_id' => $event->service->id,
            'tenant_id' => $event->service->tenant_id,
        ]);

        $payload = [
            'id' => $event->service->id,
            'tenant_id' => $event->service->tenant_id,
            'name' => $event->service->name,
            'deleted_at' => now()->toIso8601String(),
        ];

        SendEventToCrmJob::dispatch(
            'service.deleted',
            $payload,
            $event->service->tenant_id
        );
    }
}
