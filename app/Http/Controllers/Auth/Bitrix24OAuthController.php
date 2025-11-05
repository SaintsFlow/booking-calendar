<?php

namespace App\Http\Controllers\Auth;

use App\Models\Tenant;
use App\Models\TenantBitrix24Settings;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Контроллер для OAuth 2.0 авторизации через Bitrix24
 */
class Bitrix24OAuthController
{
    /**
     * Первый шаг: Перенаправление пользователя на Bitrix24 для авторизации
     * URL: https://{PORTAL}.bitrix24.ru/oauth/authorize/?client_id={CLIENT_ID}&response_type=code
     */
    public function redirectToBitrix24()
    {
        // Пытаемся получить CLIENT_ID из query параметра (для мульти-тенантности)
        $tenantDomain = request('tenant');

        if (!$tenantDomain) {
            return Inertia::render('Error', [
                'message' => 'Не указан домен тенанта. Используйте: /auth/bitrix24/redirect?tenant=ваш-домен.bitrix24.ru'
            ]);
        }

        // Ищем тенанта по домену
        $tenant = Tenant::where('bitrix24_domain', $tenantDomain)->first();

        if (!$tenant) {
            return Inertia::render('Error', [
                'message' => 'Тенант с доменом ' . $tenantDomain . ' не найден'
            ]);
        }

        // Получаем настройки Bitrix24 для тенанта
        $settings = TenantBitrix24Settings::where('tenant_id', $tenant->id)->first();

        if (!$settings || !$settings->oauth_client_id) {
            return Inertia::render('Error', [
                'message' => 'OAuth Client ID не настроен для тенанта. Настройте в разделе "Настройки" → "Bitrix24"'
            ]);
        }

        $clientId = $settings->oauth_client_id;

        // Генерируем state для защиты от CSRF
        $state = Str::random(40);
        session([
            'bitrix24_oauth_state' => $state,
            'bitrix24_oauth_tenant_id' => $tenant->id,
        ]);

        // URL для авторизации (можно использовать любой портал или oauth.bitrix.info)
        $authorizeUrl = 'https://oauth.bitrix.info/oauth/authorize/';

        $query = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'state' => $state,
        ]);

        return redirect($authorizeUrl . '?' . $query);
    }

    /**
     * Второй шаг: Обработка callback от Bitrix24
     * Bitrix24 перенаправляет на этот URL с параметрами:
     * - code: authorization code
     * - domain: домен портала (например, company.bitrix24.ru)
     * - member_id: ID участника установки приложения
     * - state: для проверки CSRF
     * - server_domain: домен сервера (bitrix24.ru, bitrix24.com и т.д.)
     */
    public function handleBitrix24Callback(Request $request)
    {
        Log::info('🔐 Bitrix24 OAuth callback received', [
            'params' => $request->all()
        ]);

        // Проверяем state для защиты от CSRF
        if ($request->state !== session('bitrix24_oauth_state')) {
            Log::error('❌ Invalid OAuth state');
            return Inertia::render('Error', [
                'message' => 'Неверный state параметр. Возможно, это CSRF атака.'
            ]);
        }

        session()->forget('bitrix24_oauth_state');

        // Проверяем наличие обязательных параметров
        if (!$request->code || !$request->domain) {
            Log::error('❌ Missing required OAuth parameters', [
                'code' => $request->code,
                'domain' => $request->domain,
            ]);

            return Inertia::render('Error', [
                'message' => 'Отсутствуют обязательные параметры авторизации'
            ]);
        }

        try {
            // Получаем access token через authorization code
            $tokenData = $this->getAccessToken(
                $request->code,
                $request->domain
            );

            Log::info('✅ Access token received', [
                'domain' => $request->domain,
                'member_id' => $request->member_id,
            ]);

            // Получаем информацию о текущем пользователе через API
            $userData = $this->getCurrentUser($tokenData['access_token'], $request->domain);

            Log::info('👤 User data received', [
                'user_id' => $userData['ID'] ?? null,
                'email' => $userData['EMAIL'] ?? null,
            ]);

            // Ищем или создаём пользователя
            $user = $this->findOrCreateUser(
                $request->domain,
                $userData,
                $request->member_id,
                $tokenData
            );

            // Авторизуем пользователя
            Auth::login($user, true);

            Log::info('✅ User logged in via Bitrix24', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);

            return redirect()->route('dashboard');
        } catch (\Exception $e) {
            Log::error('❌ Bitrix24 OAuth failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Inertia::render('Error', [
                'message' => 'Ошибка авторизации через Bitrix24: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Получить access token по authorization code
     */
    private function getAccessToken(string $code, string $domain): array
    {
        // Получаем tenant_id из сессии
        $tenantId = session('bitrix24_oauth_tenant_id');

        if (!$tenantId) {
            throw new \Exception('Tenant ID not found in session');
        }

        // Получаем настройки тенанта
        $settings = TenantBitrix24Settings::where('tenant_id', $tenantId)->first();

        if (!$settings || !$settings->oauth_client_id || !$settings->oauth_client_secret) {
            throw new \Exception('OAuth credentials not configured for tenant');
        }

        $clientId = $settings->oauth_client_id;
        $clientSecret = $settings->oauth_client_secret;

        $response = Http::asForm()->post('https://oauth.bitrix.info/oauth/token/', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get access token: ' . $response->body());
        }

        $data = $response->json();

        // Ответ содержит:
        // - access_token: токен доступа
        // - refresh_token: токен для обновления
        // - expires_in: время жизни токена (3600 секунд)
        // - scope: область доступа
        // - domain: домен портала
        // - member_id: ID участника

        return $data;
    }

    /**
     * Получить информацию о текущем пользователе
     */
    private function getCurrentUser(string $accessToken, string $domain): array
    {
        $response = Http::withToken($accessToken)
            ->get("https://{$domain}/rest/user.current.json");

        if (!$response->successful()) {
            throw new \Exception('Failed to get current user: ' . $response->body());
        }

        $data = $response->json();

        return $data['result'] ?? [];
    }

    /**
     * Найти или создать пользователя по данным из Bitrix24
     */
    private function findOrCreateUser(
        string $domain,
        array $userData,
        ?string $memberId,
        array $tokenData
    ): User {
        $bitrix24UserId = $userData['ID'] ?? null;
        $email = $userData['EMAIL'] ?? null;

        if (!$bitrix24UserId) {
            throw new \Exception('Bitrix24 user ID not provided');
        }

        // Нормализуем домен (убираем протокол и слеши)
        $normalizedDomain = str_replace(['https://', 'http://', '/'], '', $domain);

        // Ищем или создаём тенанта по домену Bitrix24
        $tenant = Tenant::firstOrCreate(
            ['bitrix24_domain' => $normalizedDomain],
            [
                'name' => $normalizedDomain,
                'bitrix24_member_id' => $memberId,
            ]
        );

        // Сохраняем токены в настройках тенанта
        $this->saveTenantTokens($tenant, $tokenData);

        // Ищем пользователя по домену тенанта и bitrix24_user_id
        $user = User::where('tenant_id', $tenant->id)
            ->where('bitrix24_user_id', $bitrix24UserId)
            ->first();

        if (!$user && $email) {
            // Если не нашли по bitrix24_user_id, пробуем по email
            $user = User::where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->first();

            if ($user) {
                // Обновляем bitrix24_user_id для существующего пользователя
                $user->update(['bitrix24_user_id' => $bitrix24UserId]);
            }
        }

        if (!$user) {
            // Создаём нового пользователя
            $name = trim(($userData['NAME'] ?? '') . ' ' . ($userData['LAST_NAME'] ?? ''));

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $name ?: 'Пользователь Bitrix24',
                'email' => $email ?: "{$bitrix24UserId}@{$normalizedDomain}",
                'bitrix24_user_id' => $bitrix24UserId,
                'password' => bcrypt(Str::random(32)), // Случайный пароль
                'role' => 'employee', // По умолчанию сотрудник
                'is_active' => true,
            ]);

            Log::info('✨ New user created from Bitrix24', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);
        }

        return $user;
    }

    /**
     * Сохранить токены OAuth в настройки тенанта
     */
    private function saveTenantTokens(Tenant $tenant, array $tokenData): void
    {
        // Обновляем webhook URL из токена (если есть)
        if (isset($tokenData['access_token'])) {
            $domain = $tokenData['domain'] ?? $tenant->bitrix24_domain;
            $webhookUrl = "https://{$domain}/rest/{$tokenData['member_id']}/{$tokenData['access_token']}/";

            $settings = $tenant->bitrix24Settings()->first();

            if ($settings) {
                $settings->update([
                    'webhook_url' => $webhookUrl,
                    'enabled' => true,
                ]);
            }
        }
    }
}
