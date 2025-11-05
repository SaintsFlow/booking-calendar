<?php

namespace App\Jobs\CRM;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendEventToCrmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Количество попыток выполнения job
     */
    public int $tries = 3;

    /**
     * Время ожидания между попытками (в секундах)
     */
    public int $backoff = 60;

    /**
     * Время жизни job (в секундах)
     */
    public int $timeout = 30;

    public function __construct(
        private string $eventType,      // 'booking.created', 'service.updated' и т.д.
        private array $payload,         // Данные события
        private int $tenantId           // ID тенанта
    ) {}

    /**
     * Выполнение job
     */
    public function handle(): void
    {
        // Проверяем, включена ли CRM интеграция
        if (!config('services.crm.enabled', false)) {
            Log::debug('CRM интеграция отключена', [
                'event_type' => $this->eventType,
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        $crmUrl = config('services.crm.webhook_url');

        if (!$crmUrl) {
            Log::warning('CRM webhook URL не настроен');
            return;
        }

        try {
            Log::info('📤 Отправка события в CRM', [
                'event_type' => $this->eventType,
                'tenant_id' => $this->tenantId,
                'payload_keys' => array_keys($this->payload),
            ]);

            $response = Http::timeout(30)
                ->retry(2, 100) // 2 повтора с задержкой 100ms
                ->post($crmUrl, [
                    'event' => $this->eventType,
                    'tenant_id' => $this->tenantId,
                    'timestamp' => now()->toIso8601String(),
                    'data' => $this->payload,
                ]);

            if ($response->successful()) {
                Log::info('✅ Событие успешно отправлено в CRM', [
                    'event_type' => $this->eventType,
                    'tenant_id' => $this->tenantId,
                    'status' => $response->status(),
                ]);
            } else {
                Log::error('❌ CRM вернул ошибку', [
                    'event_type' => $this->eventType,
                    'tenant_id' => $this->tenantId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // Бросаем исключение для повтора
                throw new \Exception('CRM returned error: ' . $response->status());
            }
        } catch (\Exception $e) {
            Log::error('❌ Ошибка отправки события в CRM', [
                'event_type' => $this->eventType,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Пробрасываем исключение для автоматического повтора
            throw $e;
        }
    }

    /**
     * Обработка провала job после всех попыток
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('🔥 Job SendEventToCrmJob провалился после всех попыток', [
            'event_type' => $this->eventType,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);

        // Здесь можно отправить уведомление админам
    }
}
