<?php

namespace App\Http\Requests\Auth;

use App\Rules\SafeCustomerPassword;
use App\Rules\UniqueAccountEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'email', 'max:255', new UniqueAccountEmail],
            'phone' => ['nullable', 'string', 'max:50', 'unique:users,phone'],
            'password' => ['bail', 'required', 'string', 'confirmed', 'max:256', Password::min(8), new SafeCustomerPassword],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }
}
