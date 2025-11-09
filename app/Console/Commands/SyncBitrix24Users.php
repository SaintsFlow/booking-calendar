<?php

namespace App\Console\Commands;

use App\Jobs\CRM\SyncUsersFromBitrix24Job;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SyncBitrix24Users extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bitrix24:sync-users {--tenant= : ID конкретного тенанта для синхронизации}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Синхронизировать пользователей из Bitrix24 для всех или конкретного тенанта';

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

            $this->info("🔄 Запуск синхронизации пользователей для тенанта: {$tenant->name}");

            if (!$tenant->bitrix24Settings?->webhook_url) {
                $this->warn("⚠️  Bitrix24 не настроен для тенанта {$tenant->name}");
                return Command::SUCCESS;
            }

            SyncUsersFromBitrix24Job::dispatch($tenant);
            $this->info("✅ Задача синхронизации добавлена в очередь");

            return Command::SUCCESS;
        }

        // Синхронизация для всех тенантов с настроенным Bitrix24
        $tenants = Tenant::whereHas('bitrix24Settings', function ($query) {
            $query->whereNotNull('webhook_url');
        })->get();

        if ($tenants->isEmpty()) {
            $this->warn("⚠️  Нет тенантов с настроенным Bitrix24");
            return Command::SUCCESS;
        }

        $this->info("🔄 Запуск синхронизации пользователей для {$tenants->count()} тенантов");

        foreach ($tenants as $tenant) {
            $this->line("  - {$tenant->name}");
            SyncUsersFromBitrix24Job::dispatch($tenant);
        }

        $this->info("✅ Задачи синхронизации добавлены в очередь");

        return Command::SUCCESS;
    }
}
