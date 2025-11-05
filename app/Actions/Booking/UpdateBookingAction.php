<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\User;
use App\Events\Booking\BookingUpdated;
use App\Actions\Schedule\ValidateBookingTimeAction;
use Illuminate\Support\Facades\DB;

class UpdateBookingAction
{
    public function __construct(
        private ValidateBookingTimeAction $validateBookingTimeAction
    ) {}

    public function execute(Booking $booking, array $data): Booking
    {
        return DB::transaction(function () use ($booking, $data) {
            // Валидируем время, если оно изменяется
            if (isset($data['start_time']) || isset($data['end_time']) || isset($data['employee_id'])) {
                $employeeId = $data['employee_id'] ?? $booking->employee_id;
                $startTime = $data['start_time'] ?? $booking->start_time;
                $endTime = $data['end_time'] ?? $booking->end_time;

                $employee = User::findOrFail($employeeId);
                $this->validateBookingTimeAction->execute(
                    $employee,
                    $startTime,
                    $endTime,
                    $booking->id // Исключаем текущую бронь из проверки
                );
            }

            // Обновляем бронирование
            $updateData = [];

            if (isset($data['client_id'])) $updateData['client_id'] = $data['client_id'];
            if (isset($data['employee_id'])) $updateData['employee_id'] = $data['employee_id'];
            if (isset($data['workplace_id'])) $updateData['workplace_id'] = $data['workplace_id'];
            if (isset($data['status_id'])) $updateData['status_id'] = $data['status_id'];
            if (isset($data['start_time'])) $updateData['start_time'] = $data['start_time'];
            if (isset($data['end_time'])) $updateData['end_time'] = $data['end_time'];
            if (isset($data['duration_minutes'])) $updateData['duration_minutes'] = $data['duration_minutes'];
            if (isset($data['total_price'])) $updateData['total_price'] = $data['total_price'];
            if (isset($data['comment'])) $updateData['comment'] = $data['comment'];
            if (isset($data['updated_by'])) $updateData['updated_by'] = $data['updated_by'];

            if (!empty($updateData)) {
                $booking->update($updateData);
            }

            // Обновляем услуги если переданы
            if (isset($data['services'])) {
                $booking->services()->detach();
                foreach ($data['services'] as $service) {
                    $booking->services()->attach($service['id'], [
                        'duration_minutes' => $service['duration_minutes'],
                        'price' => $service['price'],
                        'sort_order' => $service['sort_order'] ?? 0,
                    ]);
                }
            }

            // Обновляем связи
            $booking->refresh()->load([
                'employee',
                'client',
                'workplace',
                'status',
                'services',
            ]);

            // Генерируем событие
            event(new BookingUpdated($booking));

            return $booking;
        });
    }
}
