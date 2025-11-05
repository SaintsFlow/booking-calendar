<?php

namespace App\Infrastructure\CRM\Bitrix24;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP клиент для работы с Bitrix24 REST API
 */
class Bitrix24ApiClient
{
    private string $webhookUrl;
    private int $timeout;
    private int $retryTimes;
    private int $retryDelay;

    public function __construct(?string $webhookUrl = null)
    {
        $this->webhookUrl = $webhookUrl ?? config('services.bitrix24.webhook_url');
        $this->timeout = 30;
        $this->retryTimes = 2;
        $this->retryDelay = 100;
    }

    /**
     * Найти дубликаты контактов по телефону или email
     */
    public function findDuplicates(string $type, array $values): array
    {
        $response = $this->makeRequest('crm.duplicate.findbycomm', [
            'type' => $type,
            'values' => array_values($values),
            'entity_type' => 'CONTACT',
        ]);

        return $response['result'] ?? [];
    }

    /**
     * Создать контакт
     */
    public function createContact(array $fields): int
    {
        $response = $this->makeRequest('crm.contact.add', [
            'fields' => $fields,
        ]);

        if (!isset($response['result'])) {
            throw new \RuntimeException('Failed to create contact: ' . json_encode($response));
        }

        return (int) $response['result'];
    }

    /**
     * Получить список сделок по фильтру
     */
    public function listDeals(array $filter = [], array $select = ['*'], array $order = ['ID' => 'DESC'], int $start = 0): array
    {
        $response = $this->makeRequest('crm.deal.list', [
            'filter' => $filter,
            'select' => $select,
            'order' => $order,
            'start' => $start,
        ]);

        return $response['result'] ?? [];
    }

    /**
     * Создать сделку
     */
    public function createDeal(array $fields, array $params = []): int
    {
        $requestData = ['fields' => $fields];

        if (!empty($params)) {
            $requestData['params'] = $params;
        }

        $response = $this->makeRequest('crm.deal.add', $requestData);

        if (!isset($response['result'])) {
            throw new \RuntimeException('Failed to create deal: ' . json_encode($response));
        }

        return (int) $response['result'];
    }

    /**
     * Обновить сделку
     */
    public function updateDeal(int $dealId, array $fields): bool
    {
        $response = $this->makeRequest('crm.deal.update', [
            'id' => $dealId,
            'fields' => $fields,
        ]);

        return isset($response['result']) && $response['result'] === true;
    }

    /**
     * Получить контакт по ID
     */
    public function getContact(int $contactId): ?array
    {
        $response = $this->makeRequest('crm.contact.get', [
            'id' => $contactId,
        ]);

        return $response['result'] ?? null;
    }

    /**
     * Получить сделку по ID
     */
    public function getDeal(int $dealId): ?array
    {
        $response = $this->makeRequest('crm.deal.get', [
            'id' => $dealId,
        ]);

        return $response['result'] ?? null;
    }

    /**
     * Получить информацию о портале (для тестирования подключения)
     */
    public function getProfile(): array
    {
        $response = $this->makeRequest('profile', []);

        return $response['result'] ?? [];
    }

    /**
     * Универсальный метод для вызова любого метода API
     */
    public function call(string $method, array $params = []): array
    {
        return $this->makeRequest($method, $params);
    }

    /**
     * Получить список товаров из каталога
     */
    public function listProducts(int $iblockId, array $filter = [], int $start = 0): array
    {
        $params = [
            'select' => ['id', 'name', 'iblockId', 'active', 'price', 'quantity', 'xmlId'],
            'filter' => array_merge(['iblockId' => $iblockId], $filter),
            'start' => $start,
        ];

        $response = $this->makeRequest('catalog.product.list', $params);

        return $response['result']['products'] ?? [];
    }

    /**
     * Создать товар в каталоге
     */
    public function createProduct(int $iblockId, array $fields): ?int
    {
        $params = [
            'fields' => array_merge(['iblockId' => $iblockId], $fields),
        ];

        $response = $this->makeRequest('catalog.product.add', $params);

        return $response['result']['element']['id'] ?? null;
    }

    /**
     * Обновить товар в каталоге
     */
    public function updateProduct(int $productId, array $fields): bool
    {
        $params = [
            'id' => $productId,
            'fields' => $fields,
        ];

        $response = $this->makeRequest('catalog.product.update', $params);

        return isset($response['result']['element']);
    }

    /**
     * Получить товар по ID
     */
    public function getProduct(int $productId): ?array
    {
        $response = $this->makeRequest('catalog.product.get', ['id' => $productId]);

        return $response['result']['product'] ?? null;
    }

    /**
     * Установить товарные позиции для сделки
     */
    public function setDealProducts(int $dealId, array $rows): bool
    {
        $params = [
            'id' => $dealId,
            'rows' => $rows,
        ];

        $response = $this->makeRequest('crm.deal.productrows.set', $params);

        return $response['result'] === true;
    }

    /**
     * Выполнить запрос к Bitrix24 REST API
     */
    public function makeRequest(string $method, array $params = []): array
    {
        $url = rtrim($this->webhookUrl, '/') . '/' . $method;

        Log::debug("🔵 Bitrix24 API Request", [
            'method' => $method,
            'url' => $url,
            'params' => $params,
        ]);

        try {
            $response = $this->getHttpClient()
                ->post($url, $params)
                ->throw()
                ->json();

            Log::debug("✅ Bitrix24 API Response", [
                'method' => $method,
                'response' => $response,
            ]);

            if (isset($response['error'])) {
                Log::error("❌ Bitrix24 API Error", [
                    'method' => $method,
                    'error' => $response['error'],
                    'error_description' => $response['error_description'] ?? 'No description',
                ]);

                throw new \RuntimeException(
                    "Bitrix24 API Error [{$method}]: " .
                        ($response['error_description'] ?? $response['error'])
                );
            }

            return $response;
        } catch (\Throwable $e) {
            Log::error("🔥 Bitrix24 API Exception", [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Получить HTTP клиент с настройками
     */
    private function getHttpClient(): PendingRequest
    {
        return Http::timeout($this->timeout)
            ->retry($this->retryTimes, $this->retryDelay)
            ->acceptJson()
            ->contentType('application/json');
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function setRetry(int $times, int $delay = 100): self
    {
        $this->retryTimes = $times;
        $this->retryDelay = $delay;
        return $this;
    }
}
