<?php

namespace App\Listeners\CRM;

use App\Events\Booking\BookingDeleted;
use App\Jobs\CRM\SendEventToCrmJob;
use Illuminate\Support\Facades\Log;

class SendBookingDeletedToCrm
{
    public function handle(BookingDeleted $event): void
    {
        Log::info('🎧 Listener: Отправка BookingDeleted в CRM', [
            'booking_id' => $event->bookingData['id'] ?? null,
            'tenant_id' => $event->tenantId,
        ]);

        $payload = [
            'id' => $event->bookingData['id'],
            'tenant_id' => $event->tenantId,
            'client_id' => $event->bookingData['client_id'],
            'employee_id' => $event->bookingData['employee_id'],
            'workplace_id' => $event->bookingData['workplace_id'],
            'start_time' => $event->bookingData['start_time'],
            'deleted_at' => now()->toIso8601String(),
        ];

        SendEventToCrmJob::dispatch(
            'booking.deleted',
            $payload,
            $event->tenantId
        );
    }
}
