<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UpdateOtherItServiceRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('other_it_service_request')?->id ?? $this->route('other_it_service_request');

        return [
            'control_number' => [
                'nullable', 'string', 'max:255',
                Rule::unique('other_it_service_requests', 'control_number')->ignore($id),
            ],
            'status' => ['required', 'string', Rule::in(['pending', 'in_progress', 'completed', 'on_hold', 'cancelled'])],
            'date_of_request'  => ['nullable', 'date'],
            'department_office' => ['nullable', 'string', 'max:255'],
            'requestor_name'   => ['nullable', 'string', 'max:255'],

            'service_printing'             => ['nullable', 'boolean'],
            'service_information_material' => ['nullable', 'boolean'],
            'service_program_paper'        => ['nullable', 'boolean'],
            'service_brochure'             => ['nullable', 'boolean'],
            'service_iec_material'         => ['nullable', 'boolean'],
            'service_handbook'             => ['nullable', 'boolean'],
            'service_certificates'         => ['nullable', 'boolean'],
            'service_others'               => ['nullable', 'boolean'],
            'service_qty'                  => ['nullable', 'integer', 'min:1'],
            'service_laptop_tv_setup'      => ['nullable', 'boolean'],
            'service_others_specify'       => ['nullable', 'string'],

            'program_activity_details' => ['nullable', 'string'],
            'activity_date_text'       => ['nullable', 'string', 'max:255'],
            'activity_time'            => ['nullable', 'string', 'max:50'],

            'assigned_personnel' => ['nullable', 'string', 'max:255'],
            'date_received'      => ['nullable', 'date'],
            'action_taken'       => ['nullable', 'string'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_name'   => ['nullable', 'string', 'max:255'],
            'feedback_date'   => ['nullable', 'date'],
        ];
    }
}
