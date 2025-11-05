<?php

namespace App\Application\Booking\DTOs;

use Carbon\Carbon;

class UpdateBookingDTO
{
    public function __construct(
        public readonly ?int $workplaceId = null,
        public readonly ?int $employeeId = null,
        public readonly ?int $clientId = null,
        public readonly ?int $statusId = null,
        public readonly ?Carbon $startTime = null,
        public readonly ?Carbon $endTime = null,
        public readonly ?int $durationMinutes = null,
        public readonly ?float $totalPrice = null,
        public readonly ?string $comment = null,
        public readonly ?array $services = null,
        public readonly ?int $updatedBy = null,
    ) {}

    /**
     * Создать из массива данных
     */
    public static function fromArray(array $data): self
    {
        return new self(
            workplaceId: $data['workplace_id'] ?? null,
            employeeId: $data['employee_id'] ?? null,
            clientId: $data['client_id'] ?? null,
            statusId: $data['status_id'] ?? null,
            startTime: $data['start_time'] ?? null,
            endTime: $data['end_time'] ?? null,
            durationMinutes: $data['duration_minutes'] ?? null,
            totalPrice: $data['total_price'] ?? null,
            comment: $data['comment'] ?? null,
            services: $data['services'] ?? null,
            updatedBy: $data['updated_by'] ?? null,
        );
    }

    /**
     * Преобразовать в массив для обновления модели (только заполненные поля)
     */
    public function toArray(): array
    {
        return array_filter([
            'workplace_id' => $this->workplaceId,
            'employee_id' => $this->employeeId,
            'client_id' => $this->clientId,
            'status_id' => $this->statusId,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'duration_minutes' => $this->durationMinutes,
            'total_price' => $this->totalPrice,
            'comment' => $this->comment,
            'updated_by' => $this->updatedBy,
        ], fn($value) => $value !== null);
    }
}
