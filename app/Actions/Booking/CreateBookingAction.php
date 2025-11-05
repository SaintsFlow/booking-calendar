<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\User;
use App\Events\Booking\BookingCreated;
use App\Actions\Schedule\ValidateBookingTimeAction;
use Illuminate\Support\Facades\DB;

class CreateBookingAction
{
    public function __construct(
        private ValidateBookingTimeAction $validateBookingTimeAction
    ) {}

    public function execute(array $data): Booking
    {
        return DB::transaction(function () use ($data) {
            // Валидируем время бронирования с учетом графика работы
            $employee = User::findOrFail($data['employee_id']);
            $this->validateBookingTimeAction->execute(
                $employee,
                $data['start_time'],
                $data['end_time']
            );

            // Создаём бронирование
            $booking = Booking::create([
                'tenant_id' => $data['tenant_id'],
                'workplace_id' => $data['workplace_id'] ?? null,
                'employee_id' => $data['employee_id'],
                'client_id' => $data['client_id'],
                'status_id' => $data['status_id'],
                'created_by' => $data['created_by'] ?? null,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'duration_minutes' => $data['duration_minutes'],
                'total_price' => $data['total_price'],
                'comment' => $data['comment'] ?? null,
            ]);

            // Привязываем услуги
            if (!empty($data['services'])) {
                foreach ($data['services'] as $service) {
                    $booking->services()->attach($service['id'], [
                        'duration_minutes' => $service['duration_minutes'],
                        'price' => $service['price'],
                        'sort_order' => $service['sort_order'] ?? 0,
                    ]);
                }
            }

            // Загружаем связи
            $booking->load([
                'employee',
                'client',
                'workplace',
                'status',
                'services',
            ]);

            // Генерируем событие
            event(new BookingCreated($booking));

            return $booking;
        });
    }
}
