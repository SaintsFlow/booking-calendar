<?php

namespace App\Infrastructure\CRM\Bitrix24\Builders;

use App\Infrastructure\CRM\Bitrix24\DTO\DealData;

class DealDataBuilder
{
    private ?string $title = null;
    private ?string $typeId = null;
    private ?int $categoryId = null;
    private ?string $stageId = null;
    private ?string $currencyId = null;
    private ?float $opportunity = null;
    private ?string $isManualOpportunity = null;
    private ?int $probability = null;
    private ?int $companyId = null;
    private array $contactIds = [];
    private ?string $beginDate = null;
    private ?string $closeDate = null;
    private ?string $opened = null;
    private ?string $comments = null;
    private ?int $assignedById = null;
    private ?string $sourceId = null;
    private ?string $sourceDescription = null;
    private array $customFields = [];
    private ?string $utmSource = null;
    private ?string $utmMedium = null;
    private ?string $utmCampaign = null;
    private ?string $utmContent = null;
    private ?string $utmTerm = null;

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function setTypeId(?string $typeId): self
    {
        $this->typeId = $typeId;
        return $this;
    }

    public function setCategoryId(?int $categoryId): self
    {
        $this->categoryId = $categoryId;
        return $this;
    }

    public function setStageId(?string $stageId): self
    {
        $this->stageId = $stageId;
        return $this;
    }

    public function setCurrencyId(?string $currencyId): self
    {
        $this->currencyId = $currencyId;
        return $this;
    }

    public function setOpportunity(?float $opportunity): self
    {
        $this->opportunity = $opportunity;
        return $this;
    }

    public function setIsManualOpportunity(?string $isManualOpportunity): self
    {
        $this->isManualOpportunity = $isManualOpportunity;
        return $this;
    }

    public function setProbability(?int $probability): self
    {
        $this->probability = $probability;
        return $this;
    }

    public function setCompanyId(?int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function setContactIds(array $contactIds): self
    {
        $this->contactIds = $contactIds;
        return $this;
    }

    public function addContactId(int $contactId): self
    {
        $this->contactIds[] = $contactId;
        return $this;
    }

    public function setBeginDate(?string $beginDate): self
    {
        $this->beginDate = $beginDate;
        return $this;
    }

    public function setCloseDate(?string $closeDate): self
    {
        $this->closeDate = $closeDate;
        return $this;
    }

    public function setOpened(?string $opened): self
    {
        $this->opened = $opened;
        return $this;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;
        return $this;
    }

    public function setAssignedById(?int $assignedById): self
    {
        $this->assignedById = $assignedById;
        return $this;
    }

    public function setSourceId(?string $sourceId): self
    {
        $this->sourceId = $sourceId;
        return $this;
    }

    public function setSourceDescription(?string $sourceDescription): self
    {
        $this->sourceDescription = $sourceDescription;
        return $this;
    }

    public function setCustomField(string $fieldName, mixed $value): self
    {
        $this->customFields[$fieldName] = $value;
        return $this;
    }

    public function setUtmSource(?string $utmSource): self
    {
        $this->utmSource = $utmSource;
        return $this;
    }

    public function setUtmMedium(?string $utmMedium): self
    {
        $this->utmMedium = $utmMedium;
        return $this;
    }

    public function setUtmCampaign(?string $utmCampaign): self
    {
        $this->utmCampaign = $utmCampaign;
        return $this;
    }

    public function setUtmContent(?string $utmContent): self
    {
        $this->utmContent = $utmContent;
        return $this;
    }

    public function setUtmTerm(?string $utmTerm): self
    {
        $this->utmTerm = $utmTerm;
        return $this;
    }

    public function applyDefaults(array $defaults): self
    {
        if (isset($defaults['category_id']) && $this->categoryId === null) {
            $this->categoryId = $defaults['category_id'];
        }

        if (isset($defaults['stage_id']) && $this->stageId === null) {
            $this->stageId = $defaults['stage_id'];
        }

        if (isset($defaults['type_id']) && $this->typeId === null) {
            $this->typeId = $defaults['type_id'];
        }

        if (isset($defaults['source_id']) && $this->sourceId === null) {
            $this->sourceId = $defaults['source_id'];
        }

        if (isset($defaults['currency_id']) && $this->currencyId === null) {
            $this->currencyId = $defaults['currency_id'];
        }

        if (isset($defaults['opened']) && $this->opened === null) {
            $this->opened = $defaults['opened'];
        }

        if (isset($defaults['probability']) && $this->probability === null) {
            $this->probability = $defaults['probability'];
        }

        return $this;
    }

    public function build(): DealData
    {
        return new DealData(
            title: $this->title,
            typeId: $this->typeId,
            categoryId: $this->categoryId,
            stageId: $this->stageId,
            currencyId: $this->currencyId,
            opportunity: $this->opportunity,
            isManualOpportunity: $this->isManualOpportunity,
            probability: $this->probability,
            companyId: $this->companyId,
            contactIds: $this->contactIds,
            beginDate: $this->beginDate,
            closeDate: $this->closeDate,
            opened: $this->opened,
            comments: $this->comments,
            assignedById: $this->assignedById,
            sourceId: $this->sourceId,
            sourceDescription: $this->sourceDescription,
            customFields: $this->customFields,
            utmSource: $this->utmSource,
            utmMedium: $this->utmMedium,
            utmCampaign: $this->utmCampaign,
            utmContent: $this->utmContent,
            utmTerm: $this->utmTerm,
        );
    }
}
