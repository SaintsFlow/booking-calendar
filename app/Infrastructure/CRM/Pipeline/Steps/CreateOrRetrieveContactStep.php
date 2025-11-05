<?php

namespace App\Infrastructure\CRM\Pipeline\Steps;

use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Infrastructure\CRM\Bitrix24\DTO\PipelineContext;
use App\Infrastructure\CRM\Pipeline\PipelineStepInterface;
use Illuminate\Support\Facades\Log;

/**
 * Шаг 2: Создание контакта или получение существующих ID
 */
class CreateOrRetrieveContactStep implements PipelineStepInterface
{
    public function __construct(
        private readonly Bitrix24ApiClient $apiClient
    ) {}

    public function handle(PipelineContext $context): PipelineContext
    {
        // Если уже есть контакты - используем их
        if ($context->hasContacts()) {
            Log::info('ℹ️  Using existing contacts', [
                'contact_ids' => $context->contactIds,
            ]);
            return $context;
        }

        // Создаём новый контакт
        Log::info('➕ Creating new contact');

        $fields = $context->contactData->toArray();

        $contactId = $this->apiClient->createContact($fields);

        Log::info('✅ Contact created successfully', [
            'contact_id' => $contactId,
            'name' => $context->contactData->name,
            'phone' => $context->contactData->phone,
        ]);

        return $context->withCreatedContactId($contactId);
    }

    public function getName(): string
    {
        return 'Create or Retrieve Contact';
    }

    public function shouldExecute(PipelineContext $context): bool
    {
        // Всегда выполняем - либо используем существующие, либо создаём новый
        return true;
    }
}
