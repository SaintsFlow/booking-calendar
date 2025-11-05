<?php

namespace App\Infrastructure\CRM\Bitrix24\DTO;

class DealData
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $typeId = null,
        public readonly ?int $categoryId = null,
        public readonly ?string $stageId = null,
        public readonly ?string $currencyId = null,
        public readonly ?float $opportunity = null,
        public readonly ?string $isManualOpportunity = null,
        public readonly ?int $probability = null,
        public readonly ?int $companyId = null,
        public readonly array $contactIds = [],
        public readonly ?string $beginDate = null,
        public readonly ?string $closeDate = null,
        public readonly ?string $opened = null,
        public readonly ?string $comments = null,
        public readonly ?int $assignedById = null,
        public readonly ?string $sourceId = null,
        public readonly ?string $sourceDescription = null,
        public readonly array $customFields = [],
        public readonly ?string $utmSource = null,
        public readonly ?string $utmMedium = null,
        public readonly ?string $utmCampaign = null,
        public readonly ?string $utmContent = null,
        public readonly ?string $utmTerm = null,
    ) {}

    public function toArray(): array
    {
        $fields = [];

        if ($this->title !== null) {
            $fields['TITLE'] = $this->title;
        }

        if ($this->typeId !== null) {
            $fields['TYPE_ID'] = $this->typeId;
        }

        if ($this->categoryId !== null) {
            $fields['CATEGORY_ID'] = $this->categoryId;
        }

        if ($this->stageId !== null) {
            $fields['STAGE_ID'] = $this->stageId;
        }

        if ($this->currencyId !== null) {
            $fields['CURRENCY_ID'] = $this->currencyId;
        }

        if ($this->opportunity !== null) {
            $fields['OPPORTUNITY'] = $this->opportunity;
        }

        if ($this->isManualOpportunity !== null) {
            $fields['IS_MANUAL_OPPORTUNITY'] = $this->isManualOpportunity;
        }

        if ($this->probability !== null) {
            $fields['PROBABILITY'] = $this->probability;
        }

        if ($this->companyId !== null) {
            $fields['COMPANY_ID'] = $this->companyId;
        }

        if (!empty($this->contactIds)) {
            $fields['CONTACT_IDS'] = $this->contactIds;
        }

        if ($this->beginDate !== null) {
            $fields['BEGINDATE'] = $this->beginDate;
        }

        if ($this->closeDate !== null) {
            $fields['CLOSEDATE'] = $this->closeDate;
        }

        if ($this->opened !== null) {
            $fields['OPENED'] = $this->opened;
        }

        if ($this->comments !== null) {
            $fields['COMMENTS'] = $this->comments;
        }

        if ($this->assignedById !== null) {
            $fields['ASSIGNED_BY_ID'] = $this->assignedById;
        }

        if ($this->sourceId !== null) {
            $fields['SOURCE_ID'] = $this->sourceId;
        }

        if ($this->sourceDescription !== null) {
            $fields['SOURCE_DESCRIPTION'] = $this->sourceDescription;
        }

        if ($this->utmSource !== null) {
            $fields['UTM_SOURCE'] = $this->utmSource;
        }

        if ($this->utmMedium !== null) {
            $fields['UTM_MEDIUM'] = $this->utmMedium;
        }

        if ($this->utmCampaign !== null) {
            $fields['UTM_CAMPAIGN'] = $this->utmCampaign;
        }

        if ($this->utmContent !== null) {
            $fields['UTM_CONTENT'] = $this->utmContent;
        }

        if ($this->utmTerm !== null) {
            $fields['UTM_TERM'] = $this->utmTerm;
        }

        // Custom fields
        foreach ($this->customFields as $key => $value) {
            $fields[$key] = $value;
        }

        return $fields;
    }
}
