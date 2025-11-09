<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Auth\Bitrix24OAuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

// Broadcasting authorization routes
Broadcast::routes(['middleware' => ['web', 'auth']]);

// OAuth маршруты Bitrix24 (без middleware auth)
Route::prefix('auth/bitrix24')->name('auth.bitrix24.')->group(function () {
    Route::get('/redirect', [Bitrix24OAuthController::class, 'redirectToBitrix24'])->name('redirect');
    Route::get('/callback', [Bitrix24OAuthController::class, 'handleBitrix24Callback'])->name('callback');
});

// Гостевые роуты
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
});

// Защищённые роуты
Route::middleware(['auth'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Главная страница - редирект в зависимости от роли
    Route::get('/', function (\Illuminate\Http\Request $request) {
        if ($request->user()->role === 'super_admin') {
            return redirect()->route('admin.tenants');
        }
        return Inertia::render('Calendar/Index');
    })->name('home');
});

// Роуты требующие наличие tenant
Route::middleware(['auth', 'tenant.context', 'tenant.status'])->group(function () {
    Route::get('/calendar', fn() => Inertia::render('Calendar/Index'))->name('calendar');

    // Справочники (недоступны для сотрудников)
    Route::middleware(['not.employee'])->group(function () {
        Route::get('/clients', fn() => Inertia::render('Clients/Index'))->name('clients.index');
        Route::get('/clients/{client}', fn($client) => Inertia::render('Clients/Show', ['clientId' => $client]))->name('clients.show');

        Route::get('/services', fn() => Inertia::render('Services/Index'))->name('services.index');
        Route::get('/statuses', fn() => Inertia::render('Statuses/Index'))->name('statuses.index');
        Route::get('/workplaces', fn() => Inertia::render('Workplaces/Index'))->name('workplaces.index');
        Route::get('/users', fn() => Inertia::render('Users/Index'))->name('users.index');
    });

    // Настройки (только для администраторов)
    Route::middleware(['admin'])->prefix('settings')->name('settings.')->group(function () {
        Route::get('/bitrix24', [\App\Http\Controllers\Settings\Bitrix24SettingsController::class, 'index'])->name('bitrix24');
        Route::put('/bitrix24', [\App\Http\Controllers\Settings\Bitrix24SettingsController::class, 'update'])->name('bitrix24.update');
        Route::post('/bitrix24/test', [\App\Http\Controllers\Settings\Bitrix24SettingsController::class, 'test'])->name('bitrix24.test');
        Route::post('/bitrix24/sync-products', [\App\Http\Controllers\Settings\Bitrix24SettingsController::class, 'syncProducts'])->name('bitrix24.sync-products');
        Route::post('/bitrix24/sync-users', [\App\Http\Controllers\Settings\Bitrix24SettingsController::class, 'syncUsers'])->name('bitrix24.sync-users');
        Route::post('/bitrix24/sync-services-to', [\App\Http\Controllers\Settings\Bitrix24SettingsController::class, 'syncServicesToBitrix24'])->name('bitrix24.sync-services-to');
    });
});

// Супер-админ роуты
Route::middleware(['auth', 'super.admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/tenants', fn() => Inertia::render('Admin/Tenants'))->name('tenants');

    // Управление подписками
    Route::resource('subscriptions', \App\Http\Controllers\SubscriptionController::class);
    Route::post('/subscriptions/{subscription}/pause', [\App\Http\Controllers\SubscriptionController::class, 'pause'])->name('subscriptions.pause');
    Route::post('/subscriptions/{subscription}/resume', [\App\Http\Controllers\SubscriptionController::class, 'resume'])->name('subscriptions.resume');
    Route::post('/subscriptions/{subscription}/cancel', [\App\Http\Controllers\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');
});

// ==============================================
// API ROUTES (используют web middleware для сессий)
// ==============================================

use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\WorkplaceController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;

// API роуты с префиксом /api
Route::prefix('api')->middleware(['auth'])->group(function () {

    // Информация о текущем пользователе
    Route::get('/user', function (Request $request) {
        return $request->user()->load('tenant');
    });

    // Супер-админ API
    Route::prefix('admin')->group(function () {
        Route::get('/tenants', [TenantController::class, 'index']);
        Route::post('/tenants', [TenantController::class, 'store']);
        Route::get('/tenants/{tenant}', [TenantController::class, 'show']);
        Route::put('/tenants/{tenant}', [TenantController::class, 'update']);
        Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy']);
        Route::patch('/tenants/{tenant}/subscription', [TenantController::class, 'updateSubscription']);
        Route::post('/tenants/{tenant}/impersonate', [TenantController::class, 'impersonate']);
    });

    // Тенант API (требуют tenant context)
    Route::middleware(['tenant.required', 'tenant.context', 'tenant.status'])->group(function () {

        // Календарь
        Route::get('/calendar', [CalendarController::class, 'index']);

        // Бронирования
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::put('/bookings/{booking}', [BookingController::class, 'update']);
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
        Route::post('/bookings/{booking}/move', [BookingController::class, 'move']);
        Route::post('/bookings/{booking}/status', [BookingController::class, 'updateStatus']);
        Route::post('/bookings/{booking}/attendance', [BookingController::class, 'updateAttendance']);
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
        Route::post('/bookings/{booking}/restore', [BookingController::class, 'restore']);

        // Клиенты
        Route::get('/clients', [ClientController::class, 'index']);
        Route::post('/clients', [ClientController::class, 'store']);
        Route::get('/clients/{client}', [ClientController::class, 'show']);
        Route::put('/clients/{client}', [ClientController::class, 'update']);
        Route::delete('/clients/{client}', [ClientController::class, 'destroy']);

        // Услуги
        Route::get('/services', [ServiceController::class, 'index']);
        Route::post('/services', [ServiceController::class, 'store']);
        Route::get('/services/{service}', [ServiceController::class, 'show']);
        Route::put('/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/services/{service}', [ServiceController::class, 'destroy']);

        // Статусы
        Route::get('/statuses', [StatusController::class, 'index']);
        Route::post('/statuses', [StatusController::class, 'store']);
        Route::get('/statuses/{status}', [StatusController::class, 'show']);
        Route::put('/statuses/{status}', [StatusController::class, 'update']);
        Route::delete('/statuses/{status}', [StatusController::class, 'destroy']);

        // Места работы
        Route::get('/workplaces', [WorkplaceController::class, 'index']);
        Route::post('/workplaces', [WorkplaceController::class, 'store']);
        Route::get('/workplaces/{workplace}', [WorkplaceController::class, 'show']);
        Route::put('/workplaces/{workplace}', [WorkplaceController::class, 'update']);
        Route::delete('/workplaces/{workplace}', [WorkplaceController::class, 'destroy']);
        Route::post('/workplaces/{workplace}/employees', [WorkplaceController::class, 'syncEmployees']);

        // Пользователи
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{user}/role', [UserController::class, 'updateRole']);
    });
});
