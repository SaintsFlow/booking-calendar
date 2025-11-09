<?php

namespace App\Jobs\CRM;

use App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncUsersFromBitrix24Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Tenant $tenant
    ) {}

    public function handle(): void
    {
        try {
            $settings = $this->tenant->bitrix24Settings;

            if (!$settings || !$settings->webhook_url) {
                Log::warning("Bitrix24 не настроен для тенанта {$this->tenant->id}");
                return;
            }

            $client = new Bitrix24ApiClient($settings->webhook_url);

            $syncedCount = 0;
            $createdCount = 0;
            $updatedCount = 0;
            $start = 0;
            $limit = 50;

            do {
                // Получаем пользователей с фильтром ACTIVE = Y
                $response = $client->listUsers(['ACTIVE' => 'Y'], $start);

                if (empty($response)) {
                    break;
                }

                foreach ($response as $bitrixUser) {
                    $this->syncUser($bitrixUser, $createdCount, $updatedCount);
                    $syncedCount++;
                }

                $start += $limit;

                // Если получили меньше лимита, значит это последняя страница
                if (count($response) < $limit) {
                    break;
                }
            } while (true);

            Log::info("✅ Синхронизация пользователей завершена для тенанта {$this->tenant->id}", [
                'synced' => $syncedCount,
                'created' => $createdCount,
                'updated' => $updatedCount,
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Ошибка синхронизации пользователей для тенанта {$this->tenant->id}: {$e->getMessage()}");
            throw $e;
        }
    }

    protected function syncUser(array $bitrixUser, int &$createdCount, int &$updatedCount): void
    {
        $bitrix24UserId = $bitrixUser['ID'] ?? null;
        $email = $bitrixUser['EMAIL'] ?? null;
        $name = trim(($bitrixUser['NAME'] ?? '') . ' ' . ($bitrixUser['LAST_NAME'] ?? ''));

        if (!$bitrix24UserId) {
            Log::warning("Пропущен пользователь без ID из Bitrix24", ['user' => $bitrixUser]);
            return;
        }

        // Ищем пользователя сначала по bitrix24_user_id, затем по email
        $user = User::where('tenant_id', $this->tenant->id)
            ->where('bitrix24_user_id', $bitrix24UserId)
            ->first();

        if (!$user && $email) {
            $user = User::where('tenant_id', $this->tenant->id)
                ->where('email', $email)
                ->first();
        }

        if ($user) {
            // Обновляем существующего пользователя (БЕЗ пароля!)
            $user->update([
                'bitrix24_user_id' => $bitrix24UserId,
                'name' => $name ?: $user->name,
                'email' => $email ?: $user->email,
            ]);
            $updatedCount++;

            Log::debug("🔄 Обновлён пользователь", [
                'user_id' => $user->id,
                'bitrix24_user_id' => $bitrix24UserId,
                'name' => $name,
            ]);
        } else {
            // Создаём нового пользователя
            if (!$email) {
                Log::warning("Пропущен пользователь без email из Bitrix24", [
                    'bitrix24_user_id' => $bitrix24UserId,
                    'name' => $name,
                ]);
                return;
            }

            $user = User::create([
                'tenant_id' => $this->tenant->id,
                'bitrix24_user_id' => $bitrix24UserId,
                'name' => $name ?: 'Сотрудник ' . $bitrix24UserId,
                'email' => $email,
                'password' => bcrypt(str()->random(32)), // Случайный пароль
                'role' => 'employee',
                'is_active' => true,
            ]);
            $createdCount++;

            Log::info("➕ Создан новый пользователь из Bitrix24", [
                'user_id' => $user->id,
                'bitrix24_user_id' => $bitrix24UserId,
                'name' => $name,
                'email' => $email,
            ]);
        }
    }
}
