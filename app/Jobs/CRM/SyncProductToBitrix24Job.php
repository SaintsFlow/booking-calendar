<?php

namespace App\Jobs\CRM;

use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Models\Service;
use App\Models\TenantBitrix24Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job для синхронизации товара/услуги с Битрикс24
 */
class SyncProductToBitrix24Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Service $service
    ) {}

    /**
     * Синхронная синхронизация (без очереди)
     */
    public static function runSync(Service $service): void
    {
        $job = new self($service);
        $job->handle();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Получаем настройки интеграции для тенанта
        $settings = TenantBitrix24Settings::where('tenant_id', $this->service->tenant_id)->first();

        // Проверяем, что интеграция включена
        if (!$settings || !$settings->enabled || !$settings->webhook_url) {
            Log::info('Bitrix24 integration is disabled', [
                'service_id' => $this->service->id,
                'tenant_id' => $this->service->tenant_id,
            ]);
            return;
        }

        // Проверяем наличие catalog_iblock_id
        if (!$settings->catalog_iblock_id) {
            Log::warning('Catalog iblock_id not configured', [
                'service_id' => $this->service->id,
                'tenant_id' => $this->service->tenant_id,
            ]);
            return;
        }

        try {
            $apiClient = new Bitrix24ApiClient($settings->webhook_url);

            // Если у сервиса нет bitrix24_product_id, пытаемся найти или создать
            if (!$this->service->bitrix24_product_id) {
                $this->findOrCreateProduct($apiClient, $settings->catalog_iblock_id);
            } else {
                $this->updateProduct($apiClient);
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync product to Bitrix24', [
                'service_id' => $this->service->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Найти или создать товар в Битрикс24
     */
    private function findOrCreateProduct(Bitrix24ApiClient $apiClient, int $iblockId): void
    {
        // Ищем товар с таким же названием
        $products = $apiClient->listProducts($iblockId, ['name' => $this->service->name]);

        if (!empty($products)) {
            // Нашли товар - записываем ID
            $productId = $products[0]['id'];
            $this->service->update(['bitrix24_product_id' => $productId]);

            Log::info('Found existing product in Bitrix24', [
                'service_id' => $this->service->id,
                'product_id' => $productId,
            ]);
        } else {
            // Создаем новый товар
            $productId = $apiClient->createProduct($iblockId, [
                'name' => $this->service->name,
                'active' => $this->service->is_active ? 'Y' : 'N',
                'xmlId' => 'service_' . $this->service->id,
            ]);

            if ($productId) {
                $this->service->update(['bitrix24_product_id' => $productId]);

                Log::info('Created new product in Bitrix24', [
                    'service_id' => $this->service->id,
                    'product_id' => $productId,
                ]);
            }
        }
    }

    /**
     * Обновить существующий товар в Битрикс24
     */
    private function updateProduct(Bitrix24ApiClient $apiClient): void
    {
        $apiClient->updateProduct((int)$this->service->bitrix24_product_id, [
            'name' => $this->service->name,
            'active' => $this->service->is_active ? 'Y' : 'N',
        ]);

        Log::info('Updated product in Bitrix24', [
            'service_id' => $this->service->id,
            'product_id' => $this->service->bitrix24_product_id,
        ]);
    }
}
