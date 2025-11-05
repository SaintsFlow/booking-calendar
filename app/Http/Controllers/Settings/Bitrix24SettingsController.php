<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\CRM\SyncProductsFromBitrix24Job;
use App\Models\TenantBitrix24Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Bitrix24SettingsController extends Controller
{
    /**
     * Показать страницу настроек Bitrix24
     */
    public function index(): Response
    {
        $this->authorize('viewAny', TenantBitrix24Settings::class);

        $tenantId = auth()->user()->tenant_id;
        $settings = TenantBitrix24Settings::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'enabled' => false,
                'contact_type_id' => 'CLIENT',
                'contact_source_id' => 'WEBFORM',
                'contact_opened' => 'Y',
                'deal_category_id' => 0,
                'deal_stage_id' => 'NEW',
                'deal_type_id' => 'SALE',
                'deal_source_id' => 'WEBFORM',
                'deal_currency_id' => 'RUB',
                'deal_opened' => 'Y',
                'deal_probability' => 50,
                'max_contacts_for_deal_search' => 10,
                'max_duplicate_values' => 20,
            ]
        );

        return Inertia::render('Settings/Bitrix24', [
            'settings' => [
                'id' => $settings->id,
                'enabled' => $settings->enabled,
                'webhook_url' => $settings->webhook_url ? '***********' : null, // Скрываем URL
                'oauth_client_id' => $settings->oauth_client_id ? '***********' : null, // Скрываем ID
                'oauth_client_secret' => $settings->oauth_client_secret ? '***********' : null, // Скрываем Secret
                'catalog_iblock_id' => $settings->catalog_iblock_id,
                'contact_type_id' => $settings->contact_type_id,
                'contact_source_id' => $settings->contact_source_id,
                'contact_honorific' => $settings->contact_honorific,
                'contact_opened' => $settings->contact_opened,
                'deal_category_id' => $settings->deal_category_id,
                'deal_stage_id' => $settings->deal_stage_id,
                'deal_type_id' => $settings->deal_type_id,
                'deal_source_id' => $settings->deal_source_id,
                'deal_currency_id' => $settings->deal_currency_id,
                'deal_opened' => $settings->deal_opened,
                'deal_probability' => $settings->deal_probability,
                'max_contacts_for_deal_search' => $settings->max_contacts_for_deal_search,
                'max_duplicate_values' => $settings->max_duplicate_values,
            ],
        ]);
    }

    /**
     * Обновить настройки Bitrix24
     */
    public function update(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $settings = TenantBitrix24Settings::where('tenant_id', $tenantId)->firstOrFail();

        $this->authorize('update', $settings);

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'webhook_url' => 'nullable|string|url',
            'oauth_client_id' => 'nullable|string',
            'oauth_client_secret' => 'nullable|string',
            'catalog_iblock_id' => 'nullable|integer',
            'contact_type_id' => 'required|string',
            'contact_source_id' => 'required|string',
            'contact_honorific' => 'nullable|string',
            'contact_opened' => 'required|in:Y,N',
            'deal_category_id' => 'required|integer',
            'deal_stage_id' => 'required|string',
            'deal_type_id' => 'required|string',
            'deal_source_id' => 'required|string',
            'deal_currency_id' => 'required|string',
            'deal_opened' => 'required|in:Y,N',
            'deal_probability' => 'required|integer|min:0|max:100',
            'max_contacts_for_deal_search' => 'required|integer|min:1|max:50',
            'max_duplicate_values' => 'required|integer|min:1|max:50',
        ]);

        // Обновляем только если webhook_url изменился
        if ($request->filled('webhook_url') && $request->webhook_url !== '***********') {
            $validated['webhook_url'] = $request->webhook_url;
        } else {
            unset($validated['webhook_url']);
        }

        // Обновляем только если oauth_client_id изменился
        if ($request->filled('oauth_client_id') && $request->oauth_client_id !== '***********') {
            $validated['oauth_client_id'] = $request->oauth_client_id;
        } else {
            unset($validated['oauth_client_id']);
        }

        // Обновляем только если oauth_client_secret изменился
        if ($request->filled('oauth_client_secret') && $request->oauth_client_secret !== '***********') {
            $validated['oauth_client_secret'] = $request->oauth_client_secret;
        } else {
            unset($validated['oauth_client_secret']);
        }

        $settings->update($validated);

        return back()->with('success', 'Настройки Bitrix24 успешно обновлены');
    }

    /**
     * Тестировать подключение к Bitrix24
     */
    public function test()
    {
        $this->authorize('viewAny', TenantBitrix24Settings::class);

        $tenantId = auth()->user()->tenant_id;
        $settings = TenantBitrix24Settings::where('tenant_id', $tenantId)->firstOrFail();

        if (!$settings->enabled || !$settings->webhook_url) {
            return response()->json([
                'success' => false,
                'message' => 'Интеграция не настроена. Включите интеграцию и укажите Webhook URL.',
            ], 400);
        }

        try {
            $apiClient = new \App\Infrastructure\CRM\Bitrix24\Bitrix24ApiClient($settings->webhook_url);

            // Пробуем получить информацию о портале
            $profile = $apiClient->getProfile();

            return response()->json([
                'success' => true,
                'message' => '✅ Подключение успешно! Портал: ' . ($profile['ID'] ?? 'Unknown'),
                'data' => [
                    'portal_id' => $profile['ID'] ?? null,
                    'admin' => $profile['ADMIN'] ?? false,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка подключения: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Синхронизировать товары из Битрикс24 вручную
     */
    public function syncProducts()
    {
        $this->authorize('update', TenantBitrix24Settings::class);

        $tenantId = auth()->user()->tenant_id;
        $tenant = auth()->user()->tenant;

        // Запускаем Job для синхронизации
        SyncProductsFromBitrix24Job::dispatch($tenant);

        return response()->json([
            'success' => true,
            'message' => 'Синхронизация товаров запущена. Это может занять некоторое время.',
        ]);
    }
}
