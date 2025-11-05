<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;

class CreateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization через Policy в контроллере
    }

    public function rules(): array
    {
        return [
            'workplace_id' => 'required|exists:workplaces,id',
            'employee_id' => 'required|exists:users,id',
            'client_id' => 'required|exists:clients,id',
            'start_time' => 'required|date|after:now',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'workplace_id.required' => 'Выберите место работы',
            'employee_id.required' => 'Выберите сотрудника',
            'client_id.required' => 'Выберите клиента',
            'start_time.required' => 'Укажите время начала',
            'start_time.after' => 'Время начала должно быть в будущем',
            'service_ids.required' => 'Выберите хотя бы одну услугу',
            'service_ids.min' => 'Выберите хотя бы одну услугу',
        ];
    }
}
