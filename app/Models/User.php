<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'phone',
        'working_hours',
        'custom_schedules',
        'bitrix24_user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'working_hours' => 'array',
            'custom_schedules' => 'array',
        ];
    }

    /**
     * Проверка роли супер-админа
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Проверка роли администратора
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Проверка роли менеджера
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Проверка роли сотрудника
     */
    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    /**
     * Проверка прав администратора или выше
     */
    public function hasAdminAccess(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    /**
     * Проверка прав менеджера или выше
     */
    public function hasManagerAccess(): bool
    {
        return in_array($this->role, ['super_admin', 'admin', 'manager']);
    }

    /**
     * Тенант пользователя
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Места работы сотрудника
     */
    public function workplaces(): BelongsToMany
    {
        return $this->belongsToMany(Workplace::class, 'employee_workplaces', 'employee_id', 'workplace_id')
            ->withTimestamps();
    }

    /**
     * Бронирования, где пользователь - сотрудник
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'employee_id');
    }

    /**
     * Бронирования, созданные пользователем
     */
    public function createdBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'created_by');
    }

    /**
     * Отпуска сотрудника
     */
    public function vacations(): HasMany
    {
        return $this->hasMany(EmployeeVacation::class, 'employee_id');
    }

    /**
     * Scope для фильтрации по тенанту
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
