<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeVacation extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'tenant_id',
        'start_date',
        'end_date',
        'type',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Сотрудник
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * Тенант
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Проверка, попадает ли дата в период отпуска
     */
    public function includesDate(string $date): bool
    {
        $checkDate = \Carbon\Carbon::parse($date);
        return $checkDate->between($this->start_date, $this->end_date);
    }

    /**
     * Scope: активные отпуска на определенную дату
     */
    public function scopeActiveOnDate($query, string $date)
    {
        return $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    /**
     * Scope: отпуска конкретного сотрудника
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope: отпуска конкретного тенанта
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
