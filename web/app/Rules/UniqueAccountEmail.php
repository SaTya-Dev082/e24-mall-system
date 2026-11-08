<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueAccountEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (User::whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($value))])->exists()) {
            $fail('This email is already registered. Sign in or reset your password.');
        }
    }
}
