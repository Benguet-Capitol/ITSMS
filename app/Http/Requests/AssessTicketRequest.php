<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssessTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'findings'               => ['required', 'string'],
            'recommendations'        => ['required', 'string'],
            'reviewed_by'            => ['required', 'string', 'max:255'],
            'reviewed_by_position'   => ['required', 'string', 'max:255'],
            'replacement_available'  => ['required', 'boolean'],
            'specifications'         => ['nullable', 'string'],
            'acquisition_cost'       => 'nullable|numeric|min:0|max:999999999.99',
            'is_set'                 => ['boolean'],
            'components'             => ['nullable', 'array'],
            'components.*'           => ['string'],
            // 'assessed_by'            => ['required', 'string', 'max:255'],
            // 'assessed_by_position'   => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'findings.required' => 'The :attribute is required.',
            'recommendations.required' => 'The :attribute is required.',
            'reviewed_by.required' => 'The :attribute is required.',
            'acquisition_cost.numeric' => 'The :attribute must be a valid number.',
            'acquisition_cost.min' => 'The :attribute cannot be negative.',
        ];
    }
}
