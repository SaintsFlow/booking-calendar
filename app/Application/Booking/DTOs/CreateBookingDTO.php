<?php

namespace App\Application\Booking\DTOs;

use Carbon\Carbon;

class CreateBookingDTO
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $workplaceId,
        public readonly int $employeeId,
        public readonly int $clientId,
        public readonly int $statusId,
        public readonly int $createdBy,
        public readonly Carbon $startTime,
        public readonly Carbon $endTime,
        public readonly int $durationMinutes,
        public readonly float $totalPrice,
        public readonly ?string $comment,
        public readonly array $services, // [['id' => 1, 'duration_minutes' => 60, 'price' => 1000, 'sort_order' => 0], ...]
    ) {}

    /**
     * Создать из массива данных
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: $data['tenant_id'],
            workplaceId: $data['workplace_id'],
            employeeId: $data['employee_id'],
            clientId: $data['client_id'],
            statusId: $data['status_id'],
            createdBy: $data['created_by'],
            startTime: $data['start_time'],
            endTime: $data['end_time'],
            durationMinutes: $data['duration_minutes'],
            totalPrice: $data['total_price'],
            comment: $data['comment'] ?? null,
            services: $data['services'] ?? [],
        );
    }

    /**
     * Преобразовать в массив для создания модели
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'workplace_id' => $this->workplaceId,
            'employee_id' => $this->employeeId,
            'client_id' => $this->clientId,
            'status_id' => $this->statusId,
            'created_by' => $this->createdBy,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_minutes' => $this->durationMinutes,
            'total_price' => $this->totalPrice,
            'comment' => $this->comment,
        ];
    }
}
