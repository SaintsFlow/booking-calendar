<?php

namespace App\Infrastructure\CRM\Pipeline;

use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Infrastructure\CRM\Bitrix24\Filters\DealFilterBuilder;
use App\Infrastructure\CRM\Pipeline\Steps\CreateDealStep;
use App\Infrastructure\CRM\Pipeline\Steps\CreateOrRetrieveContactStep;
use App\Infrastructure\CRM\Pipeline\Steps\FindDuplicateContactsStep;
use App\Infrastructure\CRM\Pipeline\Steps\FindExistingDealsStep;

/**
 * Фабрика для создания CRM Pipeline с различными конфигурациями
 */
class CrmPipelineFactory
{
    /**
     * Создать стандартный Pipeline:
     * 1. Поиск дубликатов контактов
     * 2. Создание/получение контакта
     * 3. Поиск существующих сделок
     * 4. Создание сделки (если не найдены)
     */
    public static function createStandard(
        ?Bitrix24ApiClient $apiClient = null,
        ?DealFilterBuilder $dealFilter = null,
        ?int $maxDuplicates = null,
        ?int $maxContactsForSearch = null
    ): CrmPipeline {
        $apiClient = $apiClient ?? new Bitrix24ApiClient();

        return new CrmPipeline([
            new FindDuplicateContactsStep($apiClient, $maxDuplicates),
            new CreateOrRetrieveContactStep($apiClient),
            new FindExistingDealsStep($apiClient, $dealFilter, $maxContactsForSearch),
            new CreateDealStep($apiClient),
        ]);
    }

    /**
     * Создать Pipeline с принудительным созданием сделки
     * (даже если сделки уже существуют)
     */
    public static function createWithForceDeal(
        ?Bitrix24ApiClient $apiClient = null,
        ?DealFilterBuilder $dealFilter = null,
        ?int $maxDuplicates = null,
        ?int $maxContactsForSearch = null
    ): CrmPipeline {
        $apiClient = $apiClient ?? new Bitrix24ApiClient();

        return new CrmPipeline([
            new FindDuplicateContactsStep($apiClient, $maxDuplicates),
            new CreateOrRetrieveContactStep($apiClient),
            new FindExistingDealsStep($apiClient, $dealFilter, $maxContactsForSearch),
            new CreateDealStep($apiClient, forceCreate: true),
        ]);
    }

    /**
     * Создать Pipeline только для создания контакта и сделки
     * (без поиска дубликатов и существующих сделок)
     */
    public static function createContactAndDealOnly(?Bitrix24ApiClient $apiClient = null): CrmPipeline
    {
        $apiClient = $apiClient ?? new Bitrix24ApiClient();

        return new CrmPipeline([
            new CreateOrRetrieveContactStep($apiClient),
            new CreateDealStep($apiClient, forceCreate: true),
        ]);
    }

    /**
     * Создать Pipeline только для поиска контакта
     * (без создания чего-либо)
     */
    public static function createContactSearchOnly(): CrmPipeline
    {
        $apiClient = new Bitrix24ApiClient();

        return new CrmPipeline([
            new FindDuplicateContactsStep($apiClient),
        ]);
    }

    /**
     * Создать кастомный Pipeline с переданными шагами
     */
    public static function createCustom(array $steps): CrmPipeline
    {
        return new CrmPipeline($steps);
    }
}
