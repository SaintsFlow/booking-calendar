<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization через Policy в контроллере
    }

    public function rules(): array
    {
        return [
            'workplace_id' => 'sometimes|exists:workplaces,id',
            'employee_id' => 'sometimes|exists:users,id',
            'client_id' => 'sometimes|exists:clients,id',
            'status_id' => 'sometimes|exists:statuses,id',
            'start_time' => 'sometimes|date',
            'service_ids' => 'sometimes|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'workplace_id.exists' => 'Место работы не найдено',
            'employee_id.exists' => 'Сотрудник не найден',
            'client_id.exists' => 'Клиент не найден',
            'status_id.exists' => 'Статус не найден',
            'service_ids.min' => 'Выберите хотя бы одну услугу',
        ];
    }
}
