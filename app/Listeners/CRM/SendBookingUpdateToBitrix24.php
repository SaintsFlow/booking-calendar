<?php

namespace App\Listeners\CRM;

use App\Events\Booking\BookingUpdated;
use App\Jobs\CRM\UpdateBookingInBitrix24Job;
use App\Models\TenantBitrix24Settings;
use Illuminate\Support\Facades\Log;

class SendBookingUpdateToBitrix24
{
    public function handle(BookingUpdated $event): void
    {
        // Проверяем настройки Bitrix24 для тенанта
        $settings = TenantBitrix24Settings::where('tenant_id', $event->booking->tenant_id)->first();

        if (!$settings || !$settings->enabled) {
            Log::debug('⚠️ Bitrix24 интеграция отключена для тенанта', [
                'tenant_id' => $event->booking->tenant_id,
            ]);
            return;
        }

        // Проверяем, есть ли crm_deal_id (сделка уже создана в Bitrix24)
        if (!$event->booking->crm_deal_id) {
            Log::debug('⚠️ У бронирования нет crm_deal_id, обновление не требуется', [
                'booking_id' => $event->booking->id,
            ]);
            return;
        }

        Log::info('🎧 Listener: Отправка BookingUpdated в Bitrix24', [
            'booking_id' => $event->booking->id,
            'tenant_id' => $event->booking->tenant_id,
            'crm_deal_id' => $event->booking->crm_deal_id,
        ]);

        UpdateBookingInBitrix24Job::dispatch(
            $event->booking->id,
            $event->booking->tenant_id
        );
    }
}
