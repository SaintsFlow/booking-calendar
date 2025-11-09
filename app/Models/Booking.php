<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'workplace_id',
        'employee_id',
        'client_id',
        'status_id',
        'created_by',
        'updated_by',
        'start_time',
        'end_time',
        'duration_minutes',
        'total_price',
        'comment',
        'client_attended',
        'attended_at',
        'attended_by',
        'crm_deal_id', // ID сделки в CRM
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'total_price' => 'decimal:2',
        'client_attended' => 'boolean',
        'attended_at' => 'datetime',
    ];

    protected $with = ['status'];

    /**
     * Тенант
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Место работы
     */
    public function workplace(): BelongsTo
    {
        return $this->belongsTo(Workplace::class);
    }

    /**
     * Сотрудник
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Клиент
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Статус
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Создатель
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Последний редактор
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Кто отметил посещение
     */
    public function attendedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attended_by');
    }

    /**
     * Услуги бронирования
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'booking_services')
            ->withPivot(['duration_minutes', 'price', 'sort_order'])
            ->withTimestamps()
            ->orderBy('booking_services.sort_order');
    }

    /**
     * Проверка конфликта времени с другими бронированиями
     * 
     * @param int $employeeId
     * @param Carbon $startTime
     * @param Carbon $endTime
     * @param int|null $excludeBookingId
     * @return bool
     */
    public static function hasTimeConflict(
        int $employeeId,
        Carbon $startTime,
        Carbon $endTime,
        ?int $excludeBookingId = null
    ): bool {
        $query = self::where('employee_id', $employeeId)
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('start_time', [$startTime, $endTime])
                    ->orWhereBetween('end_time', [$startTime, $endTime])
                    ->orWhere(function ($q2) use ($startTime, $endTime) {
                        $q2->where('start_time', '<=', $startTime)
                            ->where('end_time', '>=', $endTime);
                    });
            });

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->exists();
    }

    /**
     * Пересчет общей стоимости и длительности
     */
    public function recalculateTotals(): void
    {
        $services = $this->services;

        $this->total_price = $services->sum('pivot.price');
        $this->duration_minutes = $services->sum('pivot.duration_minutes');

        // Пересчитываем end_time на основе start_time и duration
        $this->end_time = Carbon::parse($this->start_time)
            ->addMinutes($this->duration_minutes);

        $this->save();
    }

    /**
     * Scope для тенанта
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope для места работы
     */
    public function scopeForWorkplace($query, $workplaceId)
    {
        return $query->where('workplace_id', $workplaceId);
    }

    /**
     * Scope для сотрудника
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope для статуса
     */
    public function scopeForStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    /**
     * Scope для периода времени
     */
    public function scopeForPeriod($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->where('start_time', '>=', $startDate)
            ->where('start_time', '<=', $endDate);
    }

    /**
     * Scope: исключить отменённые бронирования
     */
    public function scopeExcludeCancelled($query)
    {
        return $query->whereHas('status', function ($q) {
            $q->whereNotIn('code', ['cancelled_by_client', 'cancelled_by_admin']);
        });
    }

    /**
     * Scope: только отменённые бронирования
     */
    public function scopeOnlyCancelled($query)
    {
        return $query->whereHas('status', function ($q) {
            $q->whereIn('code', ['cancelled_by_client', 'cancelled_by_admin']);
        });
    }

    /**
     * Проверить, отменено ли бронирование
     */
    public function isCancelled(): bool
    {
        return in_array($this->status->code ?? '', ['cancelled_by_client', 'cancelled_by_admin']);
    }

    /**
     * Можно ли восстановить бронирование
     */
    public function canBeRestored(): bool
    {
        return $this->isCancelled() && $this->start_time->isFuture();
    }
}
