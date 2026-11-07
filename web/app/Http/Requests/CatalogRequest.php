<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['availability' => ['nullable', Rule::in(['in_stock', 'unavailable'])], 'search' => ['nullable', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:255'], 'brand' => ['nullable', 'string', 'max:255'], 'min_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'], 'max_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', Rule::when($this->filled('min_price'), ['gte:min_price'])], 'sort' => ['nullable', Rule::in(['newest', 'price_low', 'price_high'])], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->view('errors.catalog', ['messages' => $validator->errors()->all()], 422));
    }
}
