<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
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
            'components'             => ['nullable', 'array'],
            'components.*'           => ['string'],
            // 'assessed_by'            => ['required', 'string', 'max:255'],
            // 'assessed_by_position'   => ['required', 'string', 'max:255'],
        ];
    }
}
