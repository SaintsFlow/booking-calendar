<?php

namespace App\Jobs\CRM;

use App\Infrastructure\CRM\Bitrix24\Builders\DealDataBuilder;
use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Models\Booking;
use App\Models\TenantBitrix24Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job для обновления сделки в Bitrix24 CRM
 */
class UpdateBookingInBitrix24Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 120;

    public function __construct(
        public readonly int $bookingId,
        public readonly int $tenantId,
    ) {}

    public function handle(): void
    {
        Log::info('🔄 Starting Bitrix24 booking update job', [
            'booking_id' => $this->bookingId,
            'tenant_id' => $this->tenantId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Получаем настройки Bitrix24 для тенанта
            $settings = TenantBitrix24Settings::where('tenant_id', $this->tenantId)->first();

            if (!$settings || !$settings->enabled) {
                Log::info('⏭️  Bitrix24 integration disabled for tenant', [
                    'tenant_id' => $this->tenantId,
                ]);
                return;
            }

            if (!$settings->webhook_url) {
                Log::warning('⚠️  Bitrix24 webhook URL not configured', [
                    'tenant_id' => $this->tenantId,
                ]);
                return;
            }

            // Загружаем бронирование с данными
            $booking = Booking::with([
                'client',
                'employee',
                'workplace',
                'services',
                'status'
            ])->findOrFail($this->bookingId);

            // Проверяем, есть ли crm_deal_id
            if (!$booking->crm_deal_id) {
                Log::warning('⚠️  Booking does not have crm_deal_id, skipping update', [
                    'booking_id' => $this->bookingId,
                ]);
                return;
            }

            // Создаём API клиент с webhook URL тенанта
            $apiClient = new Bitrix24ApiClient($settings->webhook_url);

            // 1. ПРОВЕРЯЕМ И СИНХРОНИЗИРУЕМ ТОВАРЫ
            $this->ensureProductsAreSynced($booking, $settings);

            // 2. Создаём данные сделки для обновления
            $dealData = $this->buildDealData($booking, $settings);

            // 3. Обновляем сделку через API
            $response = $apiClient->updateDeal($booking->crm_deal_id, $dealData->toArray());

            // 4. ОБНОВЛЯЕМ ТОВАРНЫЕ ПОЗИЦИИ
            $this->updateDealProducts($apiClient, $booking, $settings);

            Log::info('✅ Bitrix24 deal updated successfully', [
                'booking_id' => $this->bookingId,
                'deal_id' => $booking->crm_deal_id,
                'response' => $response,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Bitrix24 booking update job failed', [
                'booking_id' => $this->bookingId,
                'tenant_id' => $this->tenantId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('🔥 Bitrix24 booking update job failed after all retries', [
            'booking_id' => $this->bookingId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Построить данные сделки из бронирования
     */
    private function buildDealData(Booking $booking, TenantBitrix24Settings $settings): \App\Infrastructure\CRM\Bitrix24\DTO\DealData
    {
        $builder = new DealDataBuilder();

        // Формируем название сделки
        $services = $booking->services->pluck('name')->join(', ');
        $title = "Бронирование #{$booking->id}: {$services}";

        $builder
            ->setTitle($title)
            ->setOpportunity($booking->total_price)
            ->setIsManualOpportunity('Y')
            ->setBeginDate($booking->start_time->format('Y-m-d'))
            ->setCloseDate($booking->end_time->format('Y-m-d'))
            ->setComments($this->buildDealComments($booking))
            ->setCustomField('UF_CRM_BOOKING_ID', $booking->id)
            ->setCustomField('UF_CRM_BOOKING_DATE', $booking->start_time->format('d.m.Y H:i'));

        // Применяем настройки из персональных настроек тенанта
        $builder->applyDefaults($settings->toConfig()['deal'] ?? []);

        return $builder->build();
    }

    /**
     * Создать комментарий для сделки
     */
    private function buildDealComments(Booking $booking): string
    {
        $lines = [
            "[B]Детали бронирования:[/B]",
            "ID: #{$booking->id}",
            "Дата и время: {$booking->start_time->format('d.m.Y H:i')} - {$booking->end_time->format('H:i')}",
            "Длительность: {$booking->duration_minutes} мин",
            "",
            "[B]Услуги:[/B]",
        ];

        foreach ($booking->services as $service) {
            $price = $service->pivot->price ?? $service->price;
            $duration = $service->pivot->duration_minutes ?? $service->duration_minutes;
            $lines[] = "• {$service->name} ({$duration} мин) - {$price} ₽";
        }

        $lines[] = "";
        $lines[] = "[B]Итого:[/B] {$booking->total_price} ₽";

        if ($booking->comment) {
            $lines[] = "";
            $lines[] = "[B]Комментарий:[/B]";
            $lines[] = $booking->comment;
        }

        $lines[] = "";
        $lines[] = "[B]Статус:[/B] {$booking->status->name}";
        $lines[] = "[B]Сотрудник:[/B] {$booking->employee->name}";
        $lines[] = "[B]Место работы:[/B] {$booking->workplace->name}";

        return implode("\n", $lines);
    }

    /**
     * Убедиться, что все товары синхронизированы перед обновлением брони
     */
    private function ensureProductsAreSynced(Booking $booking, TenantBitrix24Settings $settings): void
    {
        // Если catalog_iblock_id не настроен, пропускаем
        if (!$settings->catalog_iblock_id) {
            return;
        }

        // Проверяем каждую услугу
        foreach ($booking->services as $service) {
            if (!$service->bitrix24_product_id) {
                Log::info('Service not synced during update, running sync now', [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'booking_id' => $booking->id,
                ]);

                // Запускаем синхронную синхронизацию
                \App\Jobs\CRM\SyncProductToBitrix24Job::runSync($service);

                // Перезагружаем сервис из БД
                $service->refresh();

                Log::info('Service synced during update', [
                    'service_id' => $service->id,
                    'bitrix24_product_id' => $service->bitrix24_product_id,
                ]);
            }
        }
    }

    /**
     * Обновить товарные позиции в сделке
     */
    private function updateDealProducts(
        Bitrix24ApiClient $apiClient,
        Booking $booking,
        TenantBitrix24Settings $settings
    ): void {
        // Если catalog_iblock_id не настроен, пропускаем
        if (!$settings->catalog_iblock_id) {
            Log::info('Catalog iblock_id not configured, skipping products update', [
                'deal_id' => $booking->crm_deal_id,
                'booking_id' => $booking->id,
            ]);
            return;
        }

        try {
            $productRows = [];

            // Перебираем все услуги в бронировании
            foreach ($booking->services as $service) {
                // Если у сервиса есть bitrix24_product_id, добавляем его
                if ($service->bitrix24_product_id) {
                    $productRows[] = [
                        'PRODUCT_ID' => (int)$service->bitrix24_product_id,
                        'PRODUCT_NAME' => $service->name,
                        'PRICE' => (float)$service->pivot->price,
                        'QUANTITY' => 1,
                    ];
                } else {
                    // Если нет ID, создаем товарную позицию без привязки к каталогу
                    $productRows[] = [
                        'PRODUCT_ID' => 0, // Не из каталога
                        'PRODUCT_NAME' => $service->name,
                        'PRICE' => (float)$service->pivot->price,
                        'QUANTITY' => 1,
                    ];

                    Log::info('Service has no bitrix24_product_id during update, adding as custom row', [
                        'service_id' => $service->id,
                        'service_name' => $service->name,
                    ]);
                }
            }

            // Обновляем товарные позиции в сделке
            if (!empty($productRows)) {
                $apiClient->setDealProducts($booking->crm_deal_id, $productRows);

                Log::info('Successfully updated products in deal', [
                    'deal_id' => $booking->crm_deal_id,
                    'booking_id' => $booking->id,
                    'products_count' => count($productRows),
                ]);
            }
        } catch (\Throwable $e) {
            // Логируем ошибку, но не прерываем выполнение
            Log::error('Failed to update products in deal', [
                'deal_id' => $booking->crm_deal_id,
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
