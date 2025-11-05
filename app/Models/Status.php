<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'color',
        'is_default',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Тенант
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Бронирования со статусом
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Проверка возможности удаления статуса
     */
    public function canDelete(): bool
    {
        // Системные статусы нельзя удалить
        if ($this->is_system) {
            return false;
        }

        // Нельзя удалить, если есть связанные бронирования
        return $this->bookings()->count() === 0;
    }

    /**
     * Scope для тенанта
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope для получения дефолтного статуса
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
