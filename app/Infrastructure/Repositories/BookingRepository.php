<?php

namespace App\Infrastructure\Repositories;

use App\Domain\Booking\Contracts\BookingRepositoryInterface;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BookingRepository implements BookingRepositoryInterface
{
    public function __construct(
        private Booking $model
    ) {}

    public function paginate(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->with(['client', 'employee', 'workplace', 'status', 'services']);

        // Фильтр по тенанту
        if (isset($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }

        // Фильтр по клиенту
        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        // Фильтр по сотруднику
        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // Фильтр по месту работы
        if (isset($filters['workplace_id'])) {
            $query->where('workplace_id', $filters['workplace_id']);
        }

        // Фильтр по статусу
        if (isset($filters['status_id'])) {
            $query->where('status_id', $filters['status_id']);
        }

        // Фильтр по периоду
        if (isset($filters['start_time'])) {
            $query->where('start_time', '>=', $filters['start_time']);
        }
        if (isset($filters['end_date'])) {
            $query->where('start_time', '<=', $filters['end_date']);
        }

        return $query->orderBy('start_time', 'desc')->paginate($perPage);
    }

    public function findById(int $id): ?Booking
    {
        return $this->model->find($id);
    }

    public function findByIdWithRelations(int $id, array $relations = []): ?Booking
    {
        $defaultRelations = [
            'employee:id,name,email',
            'client:id,first_name,last_name,phone,email',
            'workplace:id,name,address',
            'status:id,name,code,color',
            'services',
            'creator:id,name',
            'updater:id,name',
        ];

        $relations = !empty($relations) ? $relations : $defaultRelations;

        return $this->model->with($relations)->find($id);
    }

    public function create(array $data): Booking
    {
        return $this->model->create($data);
    }

    public function update(Booking $booking, array $data): Booking
    {
        $booking->update($data);
        return $booking->fresh();
    }

    public function delete(Booking $booking): bool
    {
        return $booking->delete();
    }

    public function hasTimeConflict(
        int $employeeId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeBookingId = null
    ): bool {
        return $this->model::hasTimeConflict(
            $employeeId,
            $startTime,
            $endTime,
            $excludeBookingId
        );
    }

    public function getByDateRange(
        Carbon $startDate,
        Carbon $endDate,
        ?int $tenantId = null
    ): Collection {
        $query = $this->model->query()
            ->with(['client', 'employee', 'workplace', 'status', 'services'])
            ->whereBetween('start_time', [$startDate, $endDate]);

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        return $query->orderBy('start_time')->get();
    }

    public function getEmployeeBookingsForDay(
        int $employeeId,
        Carbon $date
    ): Collection {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        return $this->model->query()
            ->with(['client', 'workplace', 'status', 'services'])
            ->where('employee_id', $employeeId)
            ->whereBetween('start_time', [$startOfDay, $endOfDay])
            ->orderBy('start_time')
            ->get();
    }

    public function getClientBookings(int $clientId, int $limit = 10): Collection
    {
        return $this->model->query()
            ->with(['status', 'employee', 'workplace'])
            ->where('client_id', $clientId)
            ->latest('start_time')
            ->limit($limit)
            ->get();
    }

    public function syncServices(Booking $booking, array $services): void
    {
        $syncData = [];
        foreach ($services as $service) {
            $syncData[$service['id']] = [
                'duration_minutes' => $service['duration_minutes'],
                'price' => $service['price'],
                'sort_order' => $service['sort_order'],
            ];
        }
        $booking->services()->sync($syncData);
    }
}
