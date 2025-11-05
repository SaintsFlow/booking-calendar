<?php

namespace App\Infrastructure\CRM\Bitrix24\Builders;

use App\Infrastructure\CRM\Bitrix24\DTO\ContactData;

class ContactDataBuilder
{
    private ?string $name = null;
    private ?string $secondName = null;
    private ?string $lastName = null;
    private ?string $phone = null;
    private ?string $email = null;
    private ?string $honorific = null;
    private ?string $typeId = null;
    private ?string $sourceId = null;
    private ?string $sourceDescription = null;
    private ?string $post = null;
    private ?string $comments = null;
    private ?string $opened = null;
    private ?int $assignedById = null;
    private ?int $companyId = null;
    private array $companyIds = [];
    private array $additionalPhones = [];
    private array $additionalEmails = [];
    private array $customFields = [];

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setSecondName(?string $secondName): self
    {
        $this->secondName = $secondName;
        return $this;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setHonorific(?string $honorific): self
    {
        $this->honorific = $honorific;
        return $this;
    }

    public function setTypeId(?string $typeId): self
    {
        $this->typeId = $typeId;
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

    public function setPost(?string $post): self
    {
        $this->post = $post;
        return $this;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;
        return $this;
    }

    public function setOpened(?string $opened): self
    {
        $this->opened = $opened;
        return $this;
    }

    public function setAssignedById(?int $assignedById): self
    {
        $this->assignedById = $assignedById;
        return $this;
    }

    public function setCompanyId(?int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function setCompanyIds(array $companyIds): self
    {
        $this->companyIds = $companyIds;
        return $this;
    }

    public function addPhone(string $phone, string $valueType = 'WORK'): self
    {
        $this->additionalPhones[] = ['VALUE' => $phone, 'VALUE_TYPE' => $valueType];
        return $this;
    }

    public function addEmail(string $email, string $valueType = 'WORK'): self
    {
        $this->additionalEmails[] = ['VALUE' => $email, 'VALUE_TYPE' => $valueType];
        return $this;
    }

    public function setCustomField(string $fieldName, mixed $value): self
    {
        $this->customFields[$fieldName] = $value;
        return $this;
    }

    public function applyDefaults(array $defaults): self
    {
        if (isset($defaults['type_id']) && $this->typeId === null) {
            $this->typeId = $defaults['type_id'];
        }

        if (isset($defaults['source_id']) && $this->sourceId === null) {
            $this->sourceId = $defaults['source_id'];
        }

        if (isset($defaults['honorific']) && $this->honorific === null) {
            $this->honorific = $defaults['honorific'];
        }

        if (isset($defaults['opened']) && $this->opened === null) {
            $this->opened = $defaults['opened'];
        }

        return $this;
    }

    public function build(): ContactData
    {
        return new ContactData(
            name: $this->name,
            secondName: $this->secondName,
            lastName: $this->lastName,
            phone: $this->phone,
            email: $this->email,
            honorific: $this->honorific,
            typeId: $this->typeId,
            sourceId: $this->sourceId,
            sourceDescription: $this->sourceDescription,
            post: $this->post,
            comments: $this->comments,
            opened: $this->opened,
            assignedById: $this->assignedById,
            companyId: $this->companyId,
            companyIds: $this->companyIds,
            additionalPhones: $this->additionalPhones,
            additionalEmails: $this->additionalEmails,
            customFields: $this->customFields,
        );
    }
}
