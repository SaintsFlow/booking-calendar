<?php

namespace App\Jobs\CRM;

use App\Infrastructure\CRM\Bitrix24\Builders\ContactDataBuilder;
use App\Infrastructure\CRM\Bitrix24\Builders\DealDataBuilder;
use App\Infrastructure\CRM\Bitrix24\DTO\PipelineContext;
use App\Infrastructure\CRM\Bitrix24\Filters\DealFilterBuilder;
use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Infrastructure\CRM\Pipeline\CrmPipelineFactory;
use App\Models\Booking;
use App\Models\TenantBitrix24Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job для создания/обновления контакта и сделки в Bitrix24 CRM
 */
class ProcessBookingInBitrix24Job implements ShouldQueue
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
        Log::info('🚀 Starting Bitrix24 job', [
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

            // Создаём API клиент с webhook URL тенанта
            $apiClient = new Bitrix24ApiClient($settings->webhook_url);

            // Загружаем бронирование с данными
            $booking = Booking::with([
                'client',
                'employee',
                'workplace',
                'services',
                'status'
            ])->findOrFail($this->bookingId);

            // 1. ПРОВЕРЯЕМ И СИНХРОНИЗИРУЕМ ТОВАРЫ
            $this->ensureProductsAreSynced($booking, $settings);

            // 2. ПРОВЕРЯЕМ/СОЗДАЕМ КОНТАКТ
            $contactId = $this->ensureContactExists($apiClient, $booking, $settings);

            // Создаём Deal Data через Builder с настройками тенанта
            $dealData = $this->buildDealData($booking, $settings);

            // Создаём Deal Filter для поиска существующих сделок
            $dealFilter = $this->buildDealFilter($booking, $settings);

            // Создаём Pipeline Context с существующим контактом
            $context = new PipelineContext(
                contactData: $this->buildContactData($booking, $settings),
                dealData: $dealData,
                tenantId: $this->tenantId,
                bookingId: $this->bookingId,
            );

            // Если у нас уже есть контакт, добавляем его в контекст
            if ($contactId) {
                $context->contactIds = [$contactId];
            }

            // Выполняем Pipeline с API клиентом тенанта и лимитами из настроек
            $pipeline = CrmPipelineFactory::createStandard(
                $apiClient,
                $dealFilter,
                $settings->max_duplicate_values,
                $settings->max_contacts_for_deal_search
            );
            $result = $pipeline->execute($context);

            // Сохраняем CRM ID в бронировании
            if ($result->createdDealId) {
                $booking->update(['crm_deal_id' => $result->createdDealId]);

                // Добавляем товары в сделку
                $this->addProductsToDeal($apiClient, $result->createdDealId, $booking, $settings);
            }

            // Сохраняем CRM ID в клиенте
            if ($result->createdContactId && $booking->client) {
                $booking->client->update(['crm_contact_id' => $result->createdContactId]);
            }

            Log::info('✅ Bitrix24 job completed successfully', [
                'booking_id' => $this->bookingId,
                'created_contact_id' => $result->createdContactId,
                'created_deal_id' => $result->createdDealId,
                'total_contacts' => count($result->contactIds),
                'total_deals' => count($result->dealIds),
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ Bitrix24 job failed', [
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
        Log::critical('🔥 Bitrix24 job failed after all retries', [
            'booking_id' => $this->bookingId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Построить данные контакта из бронирования
     */
    private function buildContactData(Booking $booking, TenantBitrix24Settings $settings): \App\Infrastructure\CRM\Bitrix24\DTO\ContactData
    {
        $client = $booking->client;

        // Разбиваем ФИО
        $nameParts = explode(' ', $client->name, 3);

        $builder = new ContactDataBuilder();
        $builder
            ->setLastName($nameParts[0] ?? '')
            ->setName($nameParts[1] ?? '')
            ->setSecondName($nameParts[2] ?? null)
            ->setPhone($client->phone)
            ->setEmail($client->email)
            ->setComments("Создано из бронирования #{$booking->id}")
            ->setSourceDescription("Бронирование от " . $booking->start_time->format('d.m.Y H:i'));

        // Применяем настройки из персональных настроек тенанта
        $builder->applyDefaults($settings->toConfig()['contact'] ?? []);

        return $builder->build();
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
     * Построить фильтр для поиска существующих сделок
     */
    private function buildDealFilter(Booking $booking, TenantBitrix24Settings $settings): DealFilterBuilder
    {
        $filter = new DealFilterBuilder();

        // Ищем только открытые сделки
        $filter->onlyOpen();

        // Фильтр по воронке из настроек тенанта
        if ($settings->deal_category_id !== null) {
            $filter->byCategoryId($settings->deal_category_id);
        }

        // Можно добавить фильтр по дате
        // $filter->createdAfter($booking->start_time->subDays(7)->format('Y-m-d'));

        return $filter;
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
            $lines[] = "- {$service->name} ({$service->pivot->duration_minutes} мин, {$service->pivot->price} ₽)";
        }

        $lines[] = "";
        $lines[] = "[B]Сотрудник:[/B] {$booking->employee->name}";
        $lines[] = "[B]Место:[/B] {$booking->workplace->name}";
        $lines[] = "[B]Статус:[/B] {$booking->status->name}";

        if ($booking->notes) {
            $lines[] = "";
            $lines[] = "[B]Заметки:[/B]";
            $lines[] = $booking->notes;
        }

        return implode("\n", $lines);
    }

    /**
     * Добавить товары в сделку
     */
    private function addProductsToDeal(
        Bitrix24ApiClient $apiClient,
        int $dealId,
        Booking $booking,
        TenantBitrix24Settings $settings
    ): void {
        // Если catalog_iblock_id не настроен, пропускаем
        if (!$settings->catalog_iblock_id) {
            Log::info('Catalog iblock_id not configured, skipping products sync', [
                'deal_id' => $dealId,
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

                    Log::info('Service has no bitrix24_product_id, adding as custom row', [
                        'service_id' => $service->id,
                        'service_name' => $service->name,
                    ]);
                }
            }

            // Если есть товарные позиции, добавляем их в сделку
            if (!empty($productRows)) {
                $apiClient->setDealProducts($dealId, $productRows);

                Log::info('Successfully added products to deal', [
                    'deal_id' => $dealId,
                    'booking_id' => $booking->id,
                    'products_count' => count($productRows),
                ]);
            }
        } catch (\Throwable $e) {
            // Логируем ошибку, но не прерываем выполнение
            Log::error('Failed to add products to deal', [
                'deal_id' => $dealId,
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Убедиться, что все товары синхронизированы перед созданием брони
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
                Log::info('Service not synced, running sync now', [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                ]);

                // Запускаем синхронную синхронизацию
                \App\Jobs\CRM\SyncProductToBitrix24Job::runSync($service);

                // Перезагружаем сервис из БД
                $service->refresh();

                Log::info('Service synced', [
                    'service_id' => $service->id,
                    'bitrix24_product_id' => $service->bitrix24_product_id,
                ]);
            }
        }
    }

    /**
     * Убедиться, что контакт существует (проверить по crm_contact_id или создать)
     */
    private function ensureContactExists(
        Bitrix24ApiClient $apiClient,
        Booking $booking,
        TenantBitrix24Settings $settings
    ): ?int {
        $client = $booking->client;

        // Если у клиента уже есть crm_contact_id, проверяем его существование
        if ($client->crm_contact_id) {
            try {
                // Пытаемся получить контакт по ID
                $response = $apiClient->call('crm.contact.get', ['id' => $client->crm_contact_id]);

                if (!empty($response['result'])) {
                    Log::info('Using existing contact', [
                        'contact_id' => $client->crm_contact_id,
                        'client_id' => $client->id,
                    ]);
                    return (int)$client->crm_contact_id;
                }
            } catch (\Throwable $e) {
                Log::warning('Contact not found by ID, will create new', [
                    'crm_contact_id' => $client->crm_contact_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Создаем новый контакт
        Log::info('Creating new contact', [
            'client_id' => $client->id,
            'client_name' => $client->name,
        ]);

        $contactData = $this->buildContactData($booking, $settings);

        try {
            $contactId = $apiClient->createContact($contactData->toArray());

            if ($contactId) {
                // Сохраняем ID в клиенте
                $client->update(['crm_contact_id' => $contactId]);

                Log::info('Contact created successfully', [
                    'contact_id' => $contactId,
                    'client_id' => $client->id,
                ]);

                return $contactId;
            }
        } catch (\Throwable $e) {
            Log::error('Failed to create contact', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
