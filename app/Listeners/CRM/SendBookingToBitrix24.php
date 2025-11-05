<?php

namespace App\Listeners\CRM;

use App\Events\Booking\BookingCreated;
use App\Jobs\CRM\ProcessBookingInBitrix24Job;
use App\Models\TenantBitrix24Settings;
use Illuminate\Support\Facades\Log;

class SendBookingToBitrix24
{
    public function handle(BookingCreated $event): void
    {
        // Проверяем настройки Bitrix24 для тенанта
        $settings = TenantBitrix24Settings::where('tenant_id', $event->booking->tenant_id)->first();

        if (!$settings || !$settings->enabled) {
            Log::debug('⚠️ Bitrix24 интеграция отключена для тенанта', [
                'tenant_id' => $event->booking->tenant_id,
            ]);
            return;
        }

        Log::info('🎧 Listener: Отправка BookingCreated в Bitrix24', [
            'booking_id' => $event->booking->id,
            'tenant_id' => $event->booking->tenant_id,
        ]);

        ProcessBookingInBitrix24Job::dispatch(
            $event->booking->id,
            $event->booking->tenant_id
        );
    }
}
