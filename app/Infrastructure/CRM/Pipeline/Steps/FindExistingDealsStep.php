<?php

namespace App\Infrastructure\CRM\Pipeline\Steps;

use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Infrastructure\CRM\Bitrix24\DTO\PipelineContext;
use App\Infrastructure\CRM\Bitrix24\Filters\DealFilterBuilder;
use App\Infrastructure\CRM\Pipeline\PipelineStepInterface;
use Illuminate\Support\Facades\Log;

/**
 * Шаг 3: Поиск существующих сделок по контактам
 */
class FindExistingDealsStep implements PipelineStepInterface
{
    public function __construct(
        private readonly Bitrix24ApiClient $apiClient,
        private readonly ?DealFilterBuilder $customFilterBuilder = null,
        private readonly ?int $maxContactsForSearch = null
    ) {}

    public function handle(PipelineContext $context): PipelineContext
    {
        if (!$context->hasContacts()) {
            Log::warning('⚠️  No contacts available for deal search');
            return $context;
        }

        $contactIds = $context->contactIds;

        // Ограничиваем количество контактов для поиска
        $maxContacts = $this->maxContactsForSearch ?? config('services.bitrix24.limits.max_contacts_for_deal_search', 10);
        if (count($contactIds) > $maxContacts) {
            Log::info("ℹ️  Limiting contact search from " . count($contactIds) . " to {$maxContacts}");
            $contactIds = array_slice($contactIds, 0, $maxContacts);
        }

        // Создаём базовый фильтр
        $filterBuilder = new DealFilterBuilder();
        $filterBuilder->byContactIds($contactIds);

        // Применяем кастомный фильтр, если передан
        if ($this->customFilterBuilder !== null) {
            $customFilters = $this->customFilterBuilder->build();
            if (!empty($customFilters)) {
                $filterBuilder->addCustomFilters($customFilters);
            }
        }

        $filter = $filterBuilder->build();

        Log::info('🔍 Searching for existing deals', [
            'contact_ids' => $contactIds,
            'filter' => $filter,
        ]);

        // Ищем сделки
        $deals = $this->apiClient->listDeals(
            filter: $filter,
            select: ['ID', 'TITLE', 'STAGE_ID', 'OPPORTUNITY'],
            order: ['ID' => 'DESC']
        );

        if (empty($deals)) {
            Log::info('ℹ️  No existing deals found');
            return $context;
        }

        $dealIds = array_column($deals, 'ID');

        Log::info('✅ Found existing deals', [
            'deal_ids' => $dealIds,
            'count' => count($dealIds),
        ]);

        return $context
            ->withDealIds($dealIds)
            ->addMetadata('existing_deals', $deals);
    }

    public function getName(): string
    {
        return 'Find Existing Deals';
    }

    public function shouldExecute(PipelineContext $context): bool
    {
        // Выполняем только если есть контакты
        return $context->hasContacts();
    }
}
