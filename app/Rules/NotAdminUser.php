<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotAdminUser implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = User::find($value);

        if (!$user) {
            return;
        }

        if ($user->role === 'admin' || $user->role === 'super_admin') {
            $fail('Администратор не может быть назначен ответственным за бронирование.');
        }
    }
}
