<?php

namespace App\Console\Commands;

use App\Jobs\CRM\SyncProductToBitrix24Job;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncServicesToBitrix24 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bitrix24:sync-services-to {--tenant= : ID конкретного тенанта для синхронизации}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Синхронизировать локальные услуги В Bitrix24 для всех или конкретного тенанта';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);

            if (!$tenant) {
                $this->error("❌ Тенант с ID {$tenantId} не найден");
                return Command::FAILURE;
            }

            $this->info("🔄 Запуск синхронизации услуг для тенанта: {$tenant->name}");

            if (!$tenant->bitrix24Settings?->webhook_url) {
                $this->warn("⚠️  Bitrix24 не настроен для тенанта {$tenant->name}");
                return Command::SUCCESS;
            }

            if (!$tenant->bitrix24Settings->catalog_iblock_id) {
                $this->warn("⚠️  Catalog IBlock ID не настроен для тенанта {$tenant->name}");
                return Command::SUCCESS;
            }

            $this->syncTenantServices($tenant);

            return Command::SUCCESS;
        }

        // Синхронизация для всех тенантов с настроенным Bitrix24
        $tenants = Tenant::whereHas('bitrix24Settings', function ($query) {
            $query->whereNotNull('webhook_url')
                ->whereNotNull('catalog_iblock_id')
                ->where('enabled', true);
        })->get();

        if ($tenants->isEmpty()) {
            $this->warn("⚠️  Нет тенантов с настроенным Bitrix24");
            return Command::SUCCESS;
        }

        $this->info("🔄 Запуск синхронизации услуг для {$tenants->count()} тенантов");

        foreach ($tenants as $tenant) {
            $this->line("  - {$tenant->name}");
            $this->syncTenantServices($tenant);
        }

        $this->info("✅ Задачи синхронизации добавлены в очередь");

        return Command::SUCCESS;
    }

    /**
     * Синхронизировать услуги тенанта
     */
    private function syncTenantServices(Tenant $tenant): void
    {
        $services = Service::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        if ($services->isEmpty()) {
            $this->line("    Нет активных услуг для синхронизации");
            return;
        }

        $this->line("    Найдено услуг: {$services->count()}");

        foreach ($services as $service) {
            SyncProductToBitrix24Job::dispatch($service);
        }

        $this->info("    ✓ {$services->count()} услуг добавлено в очередь синхронизации");
    }
}
