<?php

namespace App\Http\Requests;

use App\Models\IsChangeRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreIsChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Change Request No. and status are server-assigned on create.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'date_of_request' => ['required', 'date'],
            'department_office' => ['required', 'string', 'max:255'],
            'information_system' => ['required', 'string', 'max:255'],

            'request_type' => ['required', 'string', Rule::in(array_keys(IsChangeRequest::REQUEST_TYPES))],
            'request_type_other' => ['nullable', 'string', 'max:255', 'required_if:request_type,others'],
            'modules_affected' => ['nullable', 'string', 'max:2000'],
            'details' => ['required', 'string', 'max:10000'],
            'attachments_description' => ['required', 'string', 'max:2000'],

            'priority' => ['required', 'string', Rule::in(array_keys(IsChangeRequest::PRIORITIES))],
            'date_required' => ['required', 'date', 'after_or_equal:date_of_request'],

            'requested_by' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'request_type_other.required_if' => 'Please specify the other type of request.',
            'attachments_description.required' => 'List the documentation/sample output attached for the developer.',
            'date_required.after_or_equal' => 'The date required cannot be before the date of request.',
        ];
    }

    /**
     * Validated data with the "Others" specification cleared for the other
     * request types.
     *
     * @return array<string, mixed>
     */
    public function isChangeAttributes(): array
    {
        $data = $this->validated();

        if ($data['request_type'] !== 'others') {
            $data['request_type_other'] = null;
        }

        return $data;
    }
}
