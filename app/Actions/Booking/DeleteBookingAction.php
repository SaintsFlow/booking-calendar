<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Events\Booking\BookingDeleted;
use Illuminate\Support\Facades\DB;

class DeleteBookingAction
{
    public function execute(Booking $booking): bool
    {
        return DB::transaction(function () use ($booking) {
            $tenantId = $booking->tenant_id;
            $bookingData = $booking->toArray();

            // Отвязываем услуги
            $booking->services()->detach();

            // Удаляем бронирование
            $deleted = $booking->delete();

            if ($deleted) {
                // Генерируем событие с данными удалённой брони
                event(new BookingDeleted($bookingData, $tenantId));
            }

            return $deleted;
        });
    }
}
