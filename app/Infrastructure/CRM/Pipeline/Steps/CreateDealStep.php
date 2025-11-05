<?php

namespace App\Infrastructure\CRM\Pipeline\Steps;

use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Infrastructure\CRM\Bitrix24\DTO\PipelineContext;
use App\Infrastructure\CRM\Pipeline\PipelineStepInterface;
use Illuminate\Support\Facades\Log;

/**
 * Шаг 4: Создание новой сделки
 */
class CreateDealStep implements PipelineStepInterface
{
    public function __construct(
        private readonly Bitrix24ApiClient $apiClient,
        private readonly bool $forceCreate = false
    ) {}

    public function handle(PipelineContext $context): PipelineContext
    {
        // Если сделки уже есть и не требуется принудительное создание - пропускаем
        if ($context->hasDeals() && !$this->forceCreate) {
            Log::info('ℹ️  Deals already exist, skipping creation', [
                'deal_ids' => $context->dealIds,
            ]);
            return $context;
        }

        if (!$context->hasContacts()) {
            Log::error('❌ Cannot create deal without contacts');
            throw new \RuntimeException('Cannot create deal: no contacts available');
        }

        Log::info('➕ Creating new deal');

        // Формируем поля сделки
        $fields = $context->dealData->toArray();

        // Добавляем контакты к сделке
        if (empty($fields['CONTACT_IDS'])) {
            $fields['CONTACT_IDS'] = $context->contactIds;
        }

        // Создаём сделку
        $dealId = $this->apiClient->createDeal($fields, [
            'REGISTER_SONET_EVENT' => 'Y',
        ]);

        Log::info('✅ Deal created successfully', [
            'deal_id' => $dealId,
            'title' => $context->dealData->title,
            'contact_ids' => $fields['CONTACT_IDS'],
            'opportunity' => $context->dealData->opportunity,
        ]);

        return $context->withCreatedDealId($dealId);
    }

    public function getName(): string
    {
        return 'Create Deal';
    }

    public function shouldExecute(PipelineContext $context): bool
    {
        // Выполняем если:
        // 1. Нет существующих сделок ИЛИ
        // 2. Включен режим forceCreate
        return !$context->hasDeals() || $this->forceCreate;
    }

    /**
     * Установить режим принудительного создания
     */
    public function setForceCreate(bool $force): self
    {
        return new self($this->apiClient, $force);
    }
}
