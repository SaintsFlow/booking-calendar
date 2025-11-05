<?php

namespace App\Infrastructure\CRM\Bitrix24\Filters;

/**
 * Гибкий генератор фильтров для Bitrix24 Deal
 */
class DealFilterBuilder
{
    private array $filters = [];

    public function byContactId(int $contactId): self
    {
        $this->filters['CONTACT_ID'] = $contactId;
        return $this;
    }

    public function byContactIds(array $contactIds): self
    {
        if (!empty($contactIds)) {
            $this->filters['@CONTACT_ID'] = $contactIds;
        }
        return $this;
    }

    public function byStageId(string $stageId): self
    {
        $this->filters['STAGE_ID'] = $stageId;
        return $this;
    }

    public function byStageIds(array $stageIds): self
    {
        if (!empty($stageIds)) {
            $this->filters['@STAGE_ID'] = $stageIds;
        }
        return $this;
    }

    public function byCategoryId(int $categoryId): self
    {
        $this->filters['CATEGORY_ID'] = $categoryId;
        return $this;
    }

    public function byTitle(string $title, bool $exactMatch = false): self
    {
        if ($exactMatch) {
            $this->filters['TITLE'] = $title;
        } else {
            $this->filters['%TITLE'] = $title;
        }
        return $this;
    }

    public function byOpportunityGreaterThan(float $amount): self
    {
        $this->filters['>OPPORTUNITY'] = $amount;
        return $this;
    }

    public function byOpportunityLessThan(float $amount): self
    {
        $this->filters['<OPPORTUNITY'] = $amount;
        return $this;
    }

    public function byOpportunityBetween(float $min, float $max): self
    {
        $this->filters['>=OPPORTUNITY'] = $min;
        $this->filters['<=OPPORTUNITY'] = $max;
        return $this;
    }

    public function byAssignedUserId(int $userId): self
    {
        $this->filters['ASSIGNED_BY_ID'] = $userId;
        return $this;
    }

    public function byCompanyId(int $companyId): self
    {
        $this->filters['COMPANY_ID'] = $companyId;
        return $this;
    }

    public function byTypeId(string $typeId): self
    {
        $this->filters['TYPE_ID'] = $typeId;
        return $this;
    }

    public function byClosed(bool $closed): self
    {
        $this->filters['CLOSED'] = $closed ? 'Y' : 'N';
        return $this;
    }

    public function onlyOpen(): self
    {
        return $this->byClosed(false);
    }

    public function onlyClosed(): self
    {
        return $this->byClosed(true);
    }

    public function createdAfter(string $date): self
    {
        $this->filters['>DATE_CREATE'] = $date;
        return $this;
    }

    public function createdBefore(string $date): self
    {
        $this->filters['<DATE_CREATE'] = $date;
        return $this;
    }

    public function modifiedAfter(string $date): self
    {
        $this->filters['>DATE_MODIFY'] = $date;
        return $this;
    }

    public function bySourceId(string $sourceId): self
    {
        $this->filters['SOURCE_ID'] = $sourceId;
        return $this;
    }

    /**
     * Добавить кастомный фильтр
     */
    public function addCustomFilter(string $field, mixed $value, ?string $operator = null): self
    {
        $key = $operator ? "{$operator}{$field}" : $field;
        $this->filters[$key] = $value;
        return $this;
    }

    /**
     * Добавить несколько кастомных фильтров
     */
    public function addCustomFilters(array $filters): self
    {
        foreach ($filters as $field => $value) {
            $this->filters[$field] = $value;
        }
        return $this;
    }

    public function build(): array
    {
        return $this->filters;
    }

    public function isEmpty(): bool
    {
        return empty($this->filters);
    }
}
