<?php

namespace App\Console\Commands;

use App\Jobs\CRM\SyncProductsFromBitrix24Job;
use App\Models\Tenant;
use App\Models\TenantBitrix24Settings;
use Illuminate\Console\Command;

class SyncBitrix24Products extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bitrix24:sync-products {--tenant= : Синхронизировать только указанного тенанта}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Синхронизация товаров из Битрикс24 для всех тенантов (или указанного)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            // Синхронизируем только указанного тенанта
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Тенант с ID {$tenantId} не найден");
                return 1;
            }

            $this->syncTenant($tenant);
        } else {
            // Синхронизируем всех тенантов с активной интеграцией
            $tenants = Tenant::whereHas('bitrix24Settings', function ($query) {
                $query->where('enabled', true)
                    ->whereNotNull('webhook_url')
                    ->whereNotNull('catalog_iblock_id');
            })->get();

            $this->info("Найдено тенантов для синхронизации: " . $tenants->count());

            foreach ($tenants as $tenant) {
                $this->syncTenant($tenant);
            }
        }

        $this->info('Синхронизация завершена');
        return 0;
    }

    /**
     * Синхронизировать товары для тенанта
     */
    private function syncTenant(Tenant $tenant): void
    {
        $this->info("Синхронизация тенанта: {$tenant->name} (ID: {$tenant->id})");

        SyncProductsFromBitrix24Job::dispatch($tenant);

        $this->info("✓ Задание на синхронизацию поставлено в очередь");
    }
}
