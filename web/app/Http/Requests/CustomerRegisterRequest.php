<?php

namespace App\Http\Requests;

use App\Rules\SafeCustomerPassword;
use App\Rules\UniqueAccountEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CustomerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['bail', 'required', 'email', 'max:255', new UniqueAccountEmail], 'password' => ['bail', 'required', 'string', 'confirmed', 'max:256', Password::min(8), new SafeCustomerPassword], 'phone' => ['nullable', 'string', 'max:50', 'unique:users,phone'], 'role' => ['prohibited'], 'status' => ['prohibited'], 'admin_permissions' => ['prohibited']];
    }
}
