<?php

namespace App\Http\Requests;

use App\Models\DtsUserRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreDtsUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Control number and status are server-assigned on create. There is no
     * password rule on purpose: an `initial_password` sent by a client is
     * not validated, not fillable and never stored.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'date_of_request' => ['required', 'date'],
            'request_type' => ['required', 'string', Rule::in(array_keys(DtsUserRequest::REQUEST_TYPES))],
            'update_details' => ['nullable', 'string', 'max:2000', 'required_if:request_type,update'],

            'full_name' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'dts_username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/'],

            'requested_by' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'update_details.required_if' => 'Please state which user details should be updated.',
            'dts_username.regex' => 'The DTS username may only contain letters, numbers, dots, underscores and hyphens.',
            'requested_by.required' => 'The requesting department head is required.',
        ];
    }

    /**
     * Validated data with `update_details` cleared for new-user requests.
     *
     * @return array<string, mixed>
     */
    public function dtsUserAttributes(): array
    {
        $data = $this->validated();

        if ($data['request_type'] !== 'update') {
            $data['update_details'] = null;
        }

        return $data;
    }
}
