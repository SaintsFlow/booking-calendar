<?php

namespace App\Jobs\CRM;

use App\Models\Client;
use App\Models\TenantBitrix24Settings;
use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Infrastructure\CRM\Bitrix24\Builders\ContactDataBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncContactToBitrix24Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $clientId,
        public int $tenantId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('🔄 Starting contact sync to Bitrix24', [
            'client_id' => $this->clientId,
            'tenant_id' => $this->tenantId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Получаем настройки Bitrix24 для тенанта
            $settings = TenantBitrix24Settings::where('tenant_id', $this->tenantId)->first();

            if (!$settings || !$settings->enabled) {
                Log::info('⏭️  Bitrix24 integration disabled for tenant', [
                    'tenant_id' => $this->tenantId,
                ]);
                return;
            }

            // Загружаем клиента
            $client = Client::findOrFail($this->clientId);

            // Если у клиента уже есть CRM ID, обновляем контакт
            if ($client->crm_contact_id) {
                Log::info('📝 Contact already has CRM ID, dispatching update job', [
                    'client_id' => $this->clientId,
                    'crm_contact_id' => $client->crm_contact_id,
                ]);
                UpdateContactInBitrix24Job::dispatch($this->clientId, $this->tenantId);
                return;
            }

            // Создаём API клиент
            $apiClient = new Bitrix24ApiClient($settings->webhook_url);

            // Строим данные контакта
            $contactData = $this->buildContactData($client, $settings);

            // Создаём контакт в Bitrix24
            $response = $apiClient->createContact($contactData->toArray());

            if (isset($response['result'])) {
                $contactId = $response['result'];

                // Сохраняем CRM ID в клиенте
                $client->update(['crm_contact_id' => $contactId]);

                Log::info('✅ Contact synced successfully', [
                    'client_id' => $this->clientId,
                    'crm_contact_id' => $contactId,
                ]);
            } else {
                throw new \RuntimeException('Invalid response from Bitrix24: ' . json_encode($response));
            }
        } catch (\Throwable $e) {
            Log::error('❌ Contact sync failed', [
                'client_id' => $this->clientId,
                'tenant_id' => $this->tenantId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Построить данные контакта
     */
    private function buildContactData(Client $client, TenantBitrix24Settings $settings)
    {
        $config = $settings->toConfig();

        return (new ContactDataBuilder())
            ->setName($client->first_name)
            ->setLastName($client->last_name)
            ->setPhone($client->phone)
            ->setEmail($client->email)
            ->setComments($client->notes)
            ->setTypeId($config['contact']['type_id'])
            ->setSourceId($config['contact']['source_id'])
            ->setOpened($config['contact']['opened'])
            ->build();
    }
}
