<?php

namespace App\Infrastructure\CRM\Pipeline\Steps;

use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Infrastructure\CRM\Bitrix24\DTO\PipelineContext;
use App\Infrastructure\CRM\Pipeline\PipelineStepInterface;
use Illuminate\Support\Facades\Log;

/**
 * Шаг 1: Поиск дубликатов контактов по телефону
 */
class FindDuplicateContactsStep implements PipelineStepInterface
{
    public function __construct(
        private readonly Bitrix24ApiClient $apiClient,
        private readonly ?int $maxDuplicates = null
    ) {}

    public function handle(PipelineContext $context): PipelineContext
    {
        $phone = $context->contactData->phone;

        if (empty($phone)) {
            Log::warning('⚠️  No phone provided for duplicate search');
            return $context;
        }

        // Ищем дубликаты по телефону
        $duplicates = $this->apiClient->findDuplicates('PHONE', [$phone]);

        $contactIds = $duplicates['CONTACT'] ?? [];

        if (empty($contactIds)) {
            Log::info('🔍 No duplicate contacts found', [
                'phone' => $phone,
            ]);
            return $context;
        }

        // Ограничиваем количество контактов
        $maxContacts = $this->maxDuplicates ?? config('services.bitrix24.limits.max_duplicate_values', 20);
        if (count($contactIds) > $maxContacts) {
            Log::warning("⚠️  Found " . count($contactIds) . " contacts, limiting to {$maxContacts}");
            $contactIds = array_slice($contactIds, 0, $maxContacts);
        }

        Log::info('✅ Found duplicate contacts', [
            'phone' => $phone,
            'contact_ids' => $contactIds,
            'count' => count($contactIds),
        ]);

        return $context->withContactIds($contactIds);
    }

    public function getName(): string
    {
        return 'Find Duplicate Contacts';
    }

    public function shouldExecute(PipelineContext $context): bool
    {
        // Всегда выполняем поиск дубликатов
        return true;
    }
}
