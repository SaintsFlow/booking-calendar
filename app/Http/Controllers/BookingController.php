<?php

namespace App\Http\Controllers;

use App\Actions\Booking\CreateBookingAction;
use App\Actions\Booking\DeleteBookingAction;
use App\Actions\Booking\UpdateBookingAction;
use App\Models\Booking;
use App\Models\Status;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    /**
     * Список бронирований (для конкретного клиента или всех)
     */
    public function index(Request $request)
    {
        $query = Booking::query()
            ->with(['client', 'employee', 'workplace', 'status', 'services'])
            ->forTenant($request->user()->tenant_id);

        // Фильтр по клиенту
        if ($request->client_id) {
            $query->where('client_id', $request->client_id);
        }

        // Фильтр по сотруднику
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        // Фильтр по периоду
        if ($request->start_time) {
            $query->where('start_time', '>=', $request->start_time);
        }
        if ($request->end_date) {
            $query->where('start_time', '<=', $request->end_date);
        }

        $bookings = $query->orderBy('start_time', 'desc')->paginate(50);

        return response()->json($bookings);
    }

    /**
     * Создание бронирования
     */
    public function store(Request $request, CreateBookingAction $createBookingAction)
    {
        $this->authorize('create', Booking::class);

        $validated = $request->validate([
            'workplace_id' => 'required|exists:workplaces,id',
            'employee_id' => 'required|exists:users,id',
            'client_id' => 'required|exists:clients,id',
            'start_time' => 'required|date|after:now',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $tenantId = $request->user()->tenant_id;
        // Правильно парсим ISO время и конвертируем в локальный timezone
        $startTime = Carbon::parse($validated['start_time'])->timezone(config('app.timezone'));

        // Загружаем услуги и считаем общую длительность и стоимость
        $services = \App\Models\Service::whereIn('id', $validated['service_ids'])->get();
        $totalDuration = $services->sum('duration_minutes');
        $totalPrice = $services->sum('price');
        $endTime = $startTime->copy()->addMinutes($totalDuration);

        // Проверяем конфликты времени
        if (Booking::hasTimeConflict($validated['employee_id'], $startTime, $endTime)) {
            return response()->json([
                'message' => 'Конфликт времени: сотрудник уже занят в это время',
                'error' => 'TIME_CONFLICT',
            ], 422);
        }

        // Получаем дефолтный статус или "подтверждено"
        $defaultStatus = Status::forTenant($tenantId)->default()->first()
            ?? Status::forTenant($tenantId)->where('code', 'confirmed')->first();

        try {
            // Подготавливаем данные для Action
            $bookingData = [
                'tenant_id' => $tenantId,
                'workplace_id' => $validated['workplace_id'],
                'employee_id' => $validated['employee_id'],
                'client_id' => $validated['client_id'],
                'status_id' => $defaultStatus->id,
                'created_by' => $request->user()->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $totalDuration,
                'total_price' => $totalPrice,
                'comment' => $validated['comment'] ?? null,
                'services' => $services->map(fn($service, $index) => [
                    'id' => $service->id,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => $service->price,
                    'sort_order' => $index,
                ])->values()->toArray(),
            ];

            $booking = $createBookingAction->execute($bookingData);

            return response()->json([
                'message' => 'Бронирование успешно создано',
                'booking' => $booking,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при создании бронирования',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Просмотр бронирования
     */
    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);

        $booking->load([
            'employee:id,name,email',
            'client:id,first_name,last_name,phone,email',
            'workplace:id,name,address',
            'status:id,name,code,color',
            'services',
            'creator:id,name',
            'updater:id,name',
        ]);

        return response()->json($booking);
    }

    /**
     * Обновление бронирования
     */
    public function update(Request $request, Booking $booking, UpdateBookingAction $updateBookingAction)
    {
        $this->authorize('update', $booking);

        $validated = $request->validate([
            'workplace_id' => 'sometimes|exists:workplaces,id',
            'employee_id' => 'sometimes|exists:users,id',
            'client_id' => 'sometimes|exists:clients,id',
            'status_id' => 'sometimes|exists:statuses,id',
            'start_time' => 'sometimes|date',
            'service_ids' => 'sometimes|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        try {
            // Парсим время с учетом текущего timezone
            $startTime = isset($validated['start_time'])
                ? Carbon::parse($validated['start_time'])->timezone(config('app.timezone'))
                : $booking->start_time;

            // Если изменились услуги, пересчитываем длительность и стоимость
            if (isset($validated['service_ids'])) {
                $services = \App\Models\Service::whereIn('id', $validated['service_ids'])->get();
                $totalDuration = $services->sum('duration_minutes');
                $totalPrice = $services->sum('price');
                $endTime = $startTime->copy()->addMinutes($totalDuration);

                // Проверяем конфликты (исключая текущее бронирование)
                $employeeId = $validated['employee_id'] ?? $booking->employee_id;
                if (Booking::hasTimeConflict($employeeId, $startTime, $endTime, $booking->id)) {
                    return response()->json([
                        'message' => 'Конфликт времени: сотрудник уже занят в это время',
                        'error' => 'TIME_CONFLICT',
                    ], 422);
                }

                $validated['duration_minutes'] = $totalDuration;
                $validated['total_price'] = $totalPrice;
                $validated['start_time'] = $startTime;

                // Подготавливаем данные об услугах для Action
                $validated['services'] = $services->map(fn($service, $index) => [
                    'id' => $service->id,
                    'duration_minutes' => $service->duration_minutes,
                    'price' => $service->price,
                    'sort_order' => $index,
                ])->values()->toArray();
            } elseif (isset($validated['start_time']) || isset($validated['employee_id'])) {
                // Проверяем конфликты при изменении времени или сотрудника
                $employeeId = $validated['employee_id'] ?? $booking->employee_id;
                $endTime = $startTime->copy()->addMinutes($booking->duration_minutes);

                if (Booking::hasTimeConflict($employeeId, $startTime, $endTime, $booking->id)) {
                    return response()->json([
                        'message' => 'Конфликт времени: сотрудник уже занят в это время',
                        'error' => 'TIME_CONFLICT',
                    ], 422);
                }

                if (isset($validated['start_time'])) {
                    $validated['start_time'] = $startTime;
                }
            }

            $validated['updated_by'] = $request->user()->id;

            $booking = $updateBookingAction->execute($booking, $validated);

            Log::info('Booking updated', [
                'booking_id' => $booking->id,
                'status_id' => $booking->status_id,
                'status' => $booking->status,
                'validated_status_id' => $validated['status_id'] ?? 'not provided'
            ]);

            return response()->json([
                'message' => 'Бронирование успешно обновлено',
                'booking' => $booking,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при обновлении бронирования',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Перенос бронирования (drag & drop)
     */
    public function move(Request $request, Booking $booking, UpdateBookingAction $updateBookingAction)
    {
        $this->authorize('move', $booking);

        $validated = $request->validate([
            'start_time' => 'required|date',
            'employee_id' => 'sometimes|exists:users,id',
            'workplace_id' => 'sometimes|exists:workplaces,id',
        ]);

        $startTime = Carbon::parse($validated['start_time'])->timezone(config('app.timezone'));
        $employeeId = $validated['employee_id'] ?? $booking->employee_id;
        $endTime = $startTime->copy()->addMinutes($booking->duration_minutes);

        // Проверяем конфликты
        if (Booking::hasTimeConflict($employeeId, $startTime, $endTime, $booking->id)) {
            return response()->json([
                'message' => 'Конфликт времени: сотрудник уже занят в это время',
                'error' => 'TIME_CONFLICT',
            ], 422);
        }

        try {
            $updateData = [
                'start_time' => $startTime,
                'employee_id' => $employeeId,
                'workplace_id' => $validated['workplace_id'] ?? $booking->workplace_id,
                'updated_by' => $request->user()->id,
            ];

            $booking = $updateBookingAction->execute($booking, $updateData);

            return response()->json([
                'message' => 'Бронирование успешно перенесено',
                'booking' => $booking,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при переносе бронирования',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Изменение статуса бронирования
     */
    public function updateStatus(Request $request, Booking $booking, UpdateBookingAction $updateBookingAction)
    {
        $this->authorize('updateStatus', $booking);

        $validated = $request->validate([
            'status_id' => 'required|exists:statuses,id',
        ]);

        try {
            $updateData = [
                'status_id' => $validated['status_id'],
                'updated_by' => $request->user()->id,
            ];

            $booking = $updateBookingAction->execute($booking, $updateData);

            return response()->json([
                'message' => 'Статус успешно изменён',
                'booking' => $booking,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при изменении статуса',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Отметка посещения клиента
     */
    public function updateAttendance(Request $request, Booking $booking)
    {
        $this->authorize('updateAttendance', $booking);

        $validated = $request->validate([
            'client_attended' => 'required|boolean',
        ]);

        $booking->update([
            'client_attended' => $validated['client_attended'],
            'attended_at' => now(),
            'attended_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Статус посещения обновлён',
            'booking' => $booking,
        ]);
    }

    /**
     * Отмена/удаление бронирования
     */
    public function destroy(Booking $booking, DeleteBookingAction $deleteBookingAction)
    {
        $this->authorize('delete', $booking);

        try {
            $deleteBookingAction->execute($booking);

            return response()->json([
                'message' => 'Бронирование успешно отменено',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при удалении бронирования',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
