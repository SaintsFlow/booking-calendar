<?php

namespace App\Infrastructure\CRM\Bitrix24\DTO;

class ContactData
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $secondName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $honorific = null,
        public readonly ?string $typeId = null,
        public readonly ?string $sourceId = null,
        public readonly ?string $sourceDescription = null,
        public readonly ?string $post = null,
        public readonly ?string $comments = null,
        public readonly ?string $opened = null,
        public readonly ?int $assignedById = null,
        public readonly ?int $companyId = null,
        public readonly array $companyIds = [],
        public readonly array $additionalPhones = [],
        public readonly array $additionalEmails = [],
        public readonly array $customFields = [],
    ) {}

    public function toArray(): array
    {
        $fields = [];

        if ($this->name !== null) {
            $fields['NAME'] = $this->name;
        }

        if ($this->secondName !== null) {
            $fields['SECOND_NAME'] = $this->secondName;
        }

        if ($this->lastName !== null) {
            $fields['LAST_NAME'] = $this->lastName;
        }

        if ($this->honorific !== null) {
            $fields['HONORIFIC'] = $this->honorific;
        }

        if ($this->typeId !== null) {
            $fields['TYPE_ID'] = $this->typeId;
        }

        if ($this->sourceId !== null) {
            $fields['SOURCE_ID'] = $this->sourceId;
        }

        if ($this->sourceDescription !== null) {
            $fields['SOURCE_DESCRIPTION'] = $this->sourceDescription;
        }

        if ($this->post !== null) {
            $fields['POST'] = $this->post;
        }

        if ($this->comments !== null) {
            $fields['COMMENTS'] = $this->comments;
        }

        if ($this->opened !== null) {
            $fields['OPENED'] = $this->opened;
        }

        if ($this->assignedById !== null) {
            $fields['ASSIGNED_BY_ID'] = $this->assignedById;
        }

        if ($this->companyId !== null) {
            $fields['COMPANY_ID'] = $this->companyId;
        }

        if (!empty($this->companyIds)) {
            $fields['COMPANY_IDS'] = $this->companyIds;
        }

        // Phones
        $phones = [];
        if ($this->phone !== null) {
            $phones[] = ['VALUE' => $this->phone, 'VALUE_TYPE' => 'WORK'];
        }
        foreach ($this->additionalPhones as $phone) {
            $phones[] = $phone;
        }
        if (!empty($phones)) {
            $fields['PHONE'] = $phones;
        }

        // Emails
        $emails = [];
        if ($this->email !== null) {
            $emails[] = ['VALUE' => $this->email, 'VALUE_TYPE' => 'WORK'];
        }
        foreach ($this->additionalEmails as $email) {
            $emails[] = $email;
        }
        if (!empty($emails)) {
            $fields['EMAIL'] = $emails;
        }

        // Custom fields
        foreach ($this->customFields as $key => $value) {
            $fields[$key] = $value;
        }

        return $fields;
    }
}
