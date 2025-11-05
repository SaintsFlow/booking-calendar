<?php

namespace App\Infrastructure\CRM\Bitrix24\DTO;

/**
 * Pipeline context data object
 * Передаётся через все шаги пайплайна
 */
class PipelineContext
{
    public function __construct(
        public readonly ContactData $contactData,
        public readonly DealData $dealData,
        public readonly int $tenantId,
        public readonly ?int $bookingId = null,
        public array $contactIds = [],
        public array $dealIds = [],
        public ?int $createdContactId = null,
        public ?int $createdDealId = null,
        public array $metadata = [],
    ) {}

    public function withContactIds(array $contactIds): self
    {
        $new = clone $this;
        $new->contactIds = $contactIds;
        return $new;
    }

    public function withCreatedContactId(int $contactId): self
    {
        $new = clone $this;
        $new->createdContactId = $contactId;
        $new->contactIds[] = $contactId;
        return $new;
    }

    public function withDealIds(array $dealIds): self
    {
        $new = clone $this;
        $new->dealIds = $dealIds;
        return $new;
    }

    public function withCreatedDealId(int $dealId): self
    {
        $new = clone $this;
        $new->createdDealId = $dealId;
        $new->dealIds[] = $dealId;
        return $new;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $new = clone $this;
        $new->metadata[$key] = $value;
        return $new;
    }

    public function hasContacts(): bool
    {
        return !empty($this->contactIds);
    }

    public function hasDeals(): bool
    {
        return !empty($this->dealIds);
    }
}
