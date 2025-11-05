<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBitrix24Settings extends Model
{
    protected $fillable = [
        'tenant_id',
        'enabled',
        'webhook_url',
        'oauth_client_id',
        'oauth_client_secret',
        'catalog_iblock_id',
        'contact_type_id',
        'contact_source_id',
        'contact_honorific',
        'contact_opened',
        'deal_category_id',
        'deal_stage_id',
        'deal_type_id',
        'deal_source_id',
        'deal_currency_id',
        'deal_opened',
        'deal_probability',
        'max_contacts_for_deal_search',
        'max_duplicate_values',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'catalog_iblock_id' => 'integer',
        'deal_category_id' => 'integer',
        'deal_probability' => 'integer',
        'max_contacts_for_deal_search' => 'integer',
        'max_duplicate_values' => 'integer',
    ];

    /**
     * Получаем зашифрованные поля
     */
    protected function casts(): array
    {
        return [
            'webhook_url' => 'encrypted',
            'oauth_client_id' => 'encrypted',
            'oauth_client_secret' => 'encrypted',
        ];
    }

    /**
     * Связь с тенантом
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Получить конфигурацию в формате для Builders
     */
    public function toConfig(): array
    {
        return [
            'enabled' => $this->enabled,
            'webhook_url' => $this->webhook_url,
            'contact' => [
                'type_id' => $this->contact_type_id,
                'source_id' => $this->contact_source_id,
                'honorific' => $this->contact_honorific,
                'opened' => $this->contact_opened,
            ],
            'deal' => [
                'category_id' => $this->deal_category_id,
                'stage_id' => $this->deal_stage_id,
                'type_id' => $this->deal_type_id,
                'source_id' => $this->deal_source_id,
                'currency_id' => $this->deal_currency_id,
                'opened' => $this->deal_opened,
                'probability' => $this->deal_probability,
            ],
            'limits' => [
                'max_contacts_for_deal_search' => $this->max_contacts_for_deal_search,
                'max_duplicate_values' => $this->max_duplicate_values,
            ],
        ];
    }
}
