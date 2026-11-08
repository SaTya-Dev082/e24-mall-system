<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeCustomerPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (str_contains($value, "\0")) {
            $fail('The password contains an unsupported character.');
        }
        if (config('hashing.driver') === 'bcrypt' && strlen($value) > 72) {
            $fail('The password must not exceed 72 bytes. Non-English characters may use more than one byte.');
        }
    }
}
