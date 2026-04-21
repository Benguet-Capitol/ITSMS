<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommonProblemRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255', 'unique:common_problems,code'],
            'general_term' => ['required', 'string', 'max:255', 'unique:common_problems,general_term'],
            'information' => ['required', 'string', 'max:255'],
            'item_type_id' => ['required', 'exists:item_types,id'],
        ];
    }

    public function messages(): array {
        return [
            'code.unique'   => 'This error :attribute already exists.',
            'general_term.unique'   => 'This :attribute already exists.',
            'item_type_id.required'   => 'This :attribute is required.',
        ];
    }
}
