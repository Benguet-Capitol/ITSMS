<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreBrandModelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'name' => ['required', 'string', 'max:255', 'unique:brands,name'],
            'name' => ['nullable', 'string', 'max:255'],
            'specification' => ['string', 'max:255', 'nullable'],
            'brand_id' => ['required', 'exists:brands,id'],
            'item_type_id' => ['required', 'exists:item_types,id'],
            'image' => ['image', 'max:2048', 'nullable'],
            'year_released' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'status' => ['string'],
        ];
    }

    public function attributes(): array {
        return [
            'name' => 'Brand Name',
        ];
    }

    public function messages(): array {
        return [
            'name.unique'   => 'The :attribute already exists.',
        ];
    }
}
