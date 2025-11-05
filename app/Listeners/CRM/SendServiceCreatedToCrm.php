<?php

namespace App\Listeners\CRM;

use App\Events\Service\ServiceCreated;
use App\Jobs\CRM\SendEventToCrmJob;
use Illuminate\Support\Facades\Log;

class SendServiceCreatedToCrm
{
    public function handle(ServiceCreated $event): void
    {
        Log::info('🎧 Listener: Отправка ServiceCreated в CRM', [
            'service_id' => $event->service->id,
            'tenant_id' => $event->service->tenant_id,
        ]);

        $payload = [
            'id' => $event->service->id,
            'tenant_id' => $event->service->tenant_id,
            'name' => $event->service->name,
            'description' => $event->service->description,
            'duration_minutes' => $event->service->duration_minutes,
            'price' => $event->service->price,
            'workplace' => $event->service->workplace_id ? [
                'id' => $event->service->workplace_id,
                'name' => $event->service->workplace->name ?? '',
            ] : null,
            'is_active' => $event->service->is_active,
            'created_at' => $event->service->created_at->toIso8601String(),
        ];

        SendEventToCrmJob::dispatch(
            'service.created',
            $payload,
            $event->service->tenant_id
        );
    }
}
