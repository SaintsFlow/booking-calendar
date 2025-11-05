<?php

namespace App\Listeners\CRM;

use App\Events\Booking\BookingUpdated;
use App\Jobs\CRM\SendEventToCrmJob;
use Illuminate\Support\Facades\Log;

class SendBookingUpdatedToCrm
{
    public function handle(BookingUpdated $event): void
    {
        Log::info('🎧 Listener: Отправка BookingUpdated в CRM', [
            'booking_id' => $event->booking->id,
            'tenant_id' => $event->booking->tenant_id,
        ]);

        $payload = [
            'id' => $event->booking->id,
            'tenant_id' => $event->booking->tenant_id,
            'client' => [
                'id' => $event->booking->client_id,
                'name' => $event->booking->client->full_name ?? '',
                'phone' => $event->booking->client->phone ?? '',
                'email' => $event->booking->client->email ?? '',
            ],
            'employee' => [
                'id' => $event->booking->employee_id,
                'name' => $event->booking->employee->name ?? '',
            ],
            'workplace' => [
                'id' => $event->booking->workplace_id,
                'name' => $event->booking->workplace->name ?? '',
            ],
            'services' => $event->booking->services->map(fn($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'price' => $service->pivot->price ?? $service->price,
                'duration_minutes' => $service->pivot->duration_minutes ?? $service->duration_minutes,
            ])->toArray(),
            'start_time' => $event->booking->start_time->toIso8601String(),
            'end_time' => $event->booking->end_time->toIso8601String(),
            'duration_minutes' => $event->booking->duration_minutes,
            'total_price' => $event->booking->total_price,
            'status' => [
                'id' => $event->booking->status_id,
                'name' => $event->booking->status->name ?? '',
                'code' => $event->booking->status->code ?? '',
            ],
            'comment' => $event->booking->comment,
            'updated_at' => $event->booking->updated_at->toIso8601String(),
        ];

        SendEventToCrmJob::dispatch(
            'booking.updated',
            $payload,
            $event->booking->tenant_id
        );
    }
}
