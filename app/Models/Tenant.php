<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'domain',
        'database',
        'subscription_status',
        'trial_ends_at',
        'subscription_ends_at',
        'settings',
        'bitrix24_domain',
        'bitrix24_member_id',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    /**
     * Проверка активности подписки
     */
    public function isActive(): bool
    {
        return $this->subscription_status === 'active';
    }

    /**
     * Проверка блокировки
     */
    public function isBlocked(): bool
    {
        return $this->subscription_status === 'blocked';
    }

    /**
     * Проверка триального периода
     */
    public function isTrial(): bool
    {
        return $this->subscription_status === 'trial';
    }

    /**
     * Пользователи тенанта
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Места работы тенанта
     */
    public function workplaces(): HasMany
    {
        return $this->hasMany(Workplace::class);
    }

    /**
     * Услуги тенанта
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Клиенты тенанта
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Бронирования тенанта
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Статусы тенанта
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(Status::class);
    }

    /**
     * Настройки Bitrix24
     */
    public function bitrix24Settings()
    {
        return $this->hasOne(TenantBitrix24Settings::class);
    }

    /**
     * Создание базовых статусов для нового тенанта
     */
    public function createDefaultStatuses(): void
    {
        $defaultStatuses = [
            [
                'name' => 'Подтверждено',
                'code' => 'confirmed',
                'color' => '#10B981',
                'is_default' => true,
                'is_system' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Ожидает подтверждения',
                'code' => 'pending',
                'color' => '#F59E0B',
                'is_default' => false,
                'is_system' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Отменено клиентом',
                'code' => 'cancelled_by_client',
                'color' => '#EF4444',
                'is_default' => false,
                'is_system' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Отменено администратором',
                'code' => 'cancelled_by_admin',
                'color' => '#DC2626',
                'is_default' => false,
                'is_system' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($defaultStatuses as $status) {
            $this->statuses()->create($status);
        }
    }
}
