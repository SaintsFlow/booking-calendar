<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_name',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Связь с тенантом
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Проверка, активна ли подписка
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && (!$this->ends_at || $this->ends_at->isFuture());
    }

    /**
     * Проверка, в пробном периоде ли подписка
     */
    public function isInTrial(): bool
    {
        return $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Проверка, истекла ли подписка
     */
    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    /**
     * Scope: только активные подписки
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: подписки с истекшим сроком
     */
    public function scopeExpired($query)
    {
        return $query->where('ends_at', '<', now())
            ->whereNotNull('ends_at');
    }
}
