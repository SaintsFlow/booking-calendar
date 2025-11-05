<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workplace extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'is_active',
        'sort_order',
        'working_hours',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'working_hours' => 'array',
    ];

    /**
     * Тенант
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Сотрудники места работы
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_workplaces', 'workplace_id', 'employee_id')
            ->withTimestamps();
    }

    /**
     * Услуги, привязанные к месту работы
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Бронирования места работы
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Scope для активных мест работы
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для тенанта
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
