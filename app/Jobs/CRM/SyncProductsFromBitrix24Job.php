<?php

namespace App\Jobs\CRM;

use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantBitrix24Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job для синхронизации товаров из Битрикс24 в локальную базу
 */
class SyncProductsFromBitrix24Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Tenant $tenant
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Получаем настройки интеграции для тенанта
        $settings = TenantBitrix24Settings::where('tenant_id', $this->tenant->id)->first();

        // Проверяем, что интеграция включена
        if (!$settings || !$settings->enabled || !$settings->webhook_url) {
            Log::info('Bitrix24 integration is disabled for tenant', [
                'tenant_id' => $this->tenant->id,
            ]);
            return;
        }

        // Проверяем наличие catalog_iblock_id
        if (!$settings->catalog_iblock_id) {
            Log::warning('Catalog iblock_id not configured for tenant', [
                'tenant_id' => $this->tenant->id,
            ]);
            return;
        }

        try {
            $apiClient = new Bitrix24ApiClient($settings->webhook_url);
            $this->syncProducts($apiClient, $settings->catalog_iblock_id);
        } catch (\Exception $e) {
            Log::error('Failed to sync products from Bitrix24', [
                'tenant_id' => $this->tenant->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Синхронизировать товары из Битрикс24
     */
    private function syncProducts(Bitrix24ApiClient $apiClient, int $iblockId): void
    {
        $start = 0;
        $processed = 0;

        do {
            // Получаем товары из Битрикс24 порциями по 50
            $products = $apiClient->listProducts($iblockId, [], $start);

            foreach ($products as $product) {
                $this->syncProduct($product);
                $processed++;
            }

            $start += 50;
        } while (count($products) === 50); // Продолжаем, пока получаем полную страницу

        Log::info('Synced products from Bitrix24', [
            'tenant_id' => $this->tenant->id,
            'processed' => $processed,
        ]);
    }

    /**
     * Синхронизировать один товар
     */
    private function syncProduct(array $product): void
    {
        // Ищем локальный сервис по bitrix24_product_id
        $service = Service::where('tenant_id', $this->tenant->id)
            ->where('bitrix24_product_id', $product['id'])
            ->first();

        if ($service) {
            // Обновляем существующий сервис, если есть расхождения
            $needsUpdate = false;

            if ($service->name !== $product['name']) {
                $service->name = $product['name'];
                $needsUpdate = true;
            }

            if ($service->is_active !== ($product['active'] === 'Y')) {
                $service->is_active = $product['active'] === 'Y';
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $service->save();
                Log::info('Updated service from Bitrix24 product', [
                    'service_id' => $service->id,
                    'product_id' => $product['id'],
                ]);
            }
        } else {
            // Создаем новый сервис
            Service::create([
                'tenant_id' => $this->tenant->id,
                'bitrix24_product_id' => $product['id'],
                'name' => $product['name'],
                'is_active' => $product['active'] === 'Y',
                'type' => 'product', // Из Битрикс24 - считаем товаром
                'duration_minutes' => 60, // Значение по умолчанию
                'price' => 0, // Цена будет синхронизирована отдельно через catalog.price.*
            ]);

            Log::info('Created service from Bitrix24 product', [
                'product_id' => $product['id'],
                'name' => $product['name'],
            ]);
        }
    }
}
