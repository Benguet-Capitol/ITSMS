<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateItemTypeRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:255', 'unique:item_types,type,' . $this->item_type->id],
            'classification' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:255'],
            'is_main_inventory' => ['required', 'boolean', 'default' => false],
            'is_component' => ['required', 'boolean', 'default' => false],
            // 'part_number' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array {
        return [
            'type' => 'Type',
            'classification' => 'Classification',
            'purpose' => 'Purpose',
        ];
    }

    public function messages(): array {
        return [
            'type.unique'   => 'The :attribute already exists.',
            'classification.required'   => 'The :attribute is required.',
            'purpose.required'   => 'The :attribute is required.',
        ];
    }
}
