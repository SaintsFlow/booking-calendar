<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\WorkplaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Публичные роуты (без аутентификации)
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Защищённые роуты (требуют аутентификации)
Route::middleware(['auth:web'])->group(function () {

    // Информация о текущем пользователе
    Route::get('/user', function (Request $request) {
        return $request->user()->load('tenant');
    });

    // ============================================
    // СУПЕР-АДМИН: Управление тенантами
    // ============================================
    Route::prefix('admin')->middleware(['super.admin'])->group(function () {
        Route::apiResource('tenants', TenantController::class);
        Route::post('tenants/{tenant}/subscription', [TenantController::class, 'updateSubscription']);
        Route::post('tenants/{tenant}/impersonate', [TenantController::class, 'impersonate']);
    });

    // ============================================
    // ТЕНАНТ: Основные роуты
    // ============================================
    Route::middleware([
        \App\Http\Middleware\EnsureUserHasTenant::class,
        \App\Http\Middleware\SetTenantContext::class,
        \App\Http\Middleware\CheckTenantStatus::class,
    ])->group(function () {

        // Календарь (просмотр доступен всем ролям)
        Route::get('calendar', [CalendarController::class, 'index']);
        Route::get('calendar/available-slots', [CalendarController::class, 'getAvailableSlots']);
        Route::post('calendar/check-conflict', [CalendarController::class, 'checkTimeConflict']);

        // Бронирования
        Route::prefix('bookings')->group(function () {
            Route::post('/', [BookingController::class, 'store']);
            Route::get('/{booking}', [BookingController::class, 'show']);
            Route::put('/{booking}', [BookingController::class, 'update']);
            Route::delete('/{booking}', [BookingController::class, 'destroy']);

            // Специальные действия с бронированиями
            Route::post('/{booking}/move', [BookingController::class, 'move']);
            Route::post('/{booking}/status', [BookingController::class, 'updateStatus']);
            Route::post('/{booking}/attendance', [BookingController::class, 'updateAttendance']);
        });

        // Клиенты (доступно менеджерам и администраторам)
        Route::apiResource('clients', ClientController::class);

        // Услуги (доступно менеджерам и администраторам)
        Route::apiResource('services', ServiceController::class);

        // Статусы (доступно администраторам)
        Route::apiResource('statuses', StatusController::class);

        // Места работы (управление доступно администраторам и менеджерам)
        Route::prefix('workplaces')->middleware(['admin.or.manager'])->group(function () {
            Route::get('/', [WorkplaceController::class, 'index']);
            Route::post('/', [WorkplaceController::class, 'store']);
            Route::get('/{workplace}', [WorkplaceController::class, 'show']);
            Route::put('/{workplace}', [WorkplaceController::class, 'update']);
            Route::delete('/{workplace}', [WorkplaceController::class, 'destroy']);
            Route::post('/{workplace}/employees', [WorkplaceController::class, 'syncEmployees']);
        });

        // Пользователи (управление доступно администраторам и менеджерам)
        Route::prefix('users')->middleware(['admin.or.manager'])->group(function () {
            Route::get('/', [\App\Http\Controllers\UserController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\UserController::class, 'store']);
            Route::get('/{user}', [\App\Http\Controllers\UserController::class, 'show']);
            Route::put('/{user}', [\App\Http\Controllers\UserController::class, 'update']);
            Route::delete('/{user}', [\App\Http\Controllers\UserController::class, 'destroy']);
            Route::post('/{user}/role', [\App\Http\Controllers\UserController::class, 'updateRole']);
        });

        // Расписание и доступное время
        Route::prefix('schedule')->group(function () {
            Route::get('/available-hours', [ScheduleController::class, 'getAvailableHours']);
            Route::get('/available-slots', [ScheduleController::class, 'getAvailableTimeSlots']);
            Route::get('/weekly', [ScheduleController::class, 'getWeeklySchedule']);
        });

        // Отпуска (только для администраторов и менеджеров)
        Route::middleware(['admin.or.manager'])->group(function () {
            Route::apiResource('vacations', VacationController::class);
        });
    });
});
