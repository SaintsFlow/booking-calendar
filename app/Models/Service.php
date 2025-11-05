<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'workplace_id',
        'name',
        'description',
        'duration_minutes',
        'price',
        'is_active',
        'bitrix24_product_id',
        'type',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'duration', // Добавляем короткое имя для фронтенда
    ];

    /**
     * Аксессор для duration (alias для duration_minutes)
     */
    public function getDurationAttribute()
    {
        return $this->duration_minutes;
    }

    /**
     * Тенант
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Место работы (опционально)
     */
    public function workplace(): BelongsTo
    {
        return $this->belongsTo(Workplace::class);
    }

    /**
     * Бронирования, использующие услугу
     */
    public function bookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'booking_services')
            ->withPivot(['duration_minutes', 'price', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Scope для активных услуг
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

    /**
     * Scope для места работы
     */
    public function scopeForWorkplace($query, $workplaceId)
    {
        return $query->where(function ($q) use ($workplaceId) {
            $q->where('workplace_id', $workplaceId)
                ->orWhereNull('workplace_id');
        });
    }
}
