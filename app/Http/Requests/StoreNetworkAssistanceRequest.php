<?php

namespace App\Http\Requests;

use App\Models\NetworkAssistanceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreNetworkAssistanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Control number and status are server-assigned on create.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'date_of_request' => ['required', 'date'],
            'department_office' => ['required', 'string', 'max:255'],
            'requestor_name' => ['required', 'string', 'max:255'],

            'service_wifi_installation' => ['boolean'],
            'service_ethernet_wiring' => ['boolean'],
            'service_network_planning' => ['boolean'],
            'service_other_support' => ['boolean'],
            'service_other_specify' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $this->boolean('service_other_support')),
            ],
            'services' => ['array', 'min:1'],

            'program_activity_details' => ['required', 'string', 'max:5000'],
            'activity_date' => ['required', 'date'],
            'activity_end_date' => ['nullable', 'date', 'after_or_equal:activity_date'],
            'activity_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'activity_end_date.after_or_equal' => 'The end date cannot be before the date of the activity.',
            'services.min' => 'Select at least one network service.',
            'service_other_specify.required' => 'Please specify the other network IT support needed.',
        ];
    }

    /**
     * Normalizes the service checkboxes (absent = unticked), derives
     * `services` -- the ticked ones -- so "pick at least one" has its own
     * error key, and drops the "Other" specification when Other is
     * unticked. `services` isn't fillable, so it's never saved.
     */
    protected function prepareForValidation(): void
    {
        $services = [];

        foreach (array_keys(NetworkAssistanceRequest::SERVICES) as $field) {
            $ticked = $this->boolean($field);
            $this->merge([$field => $ticked]);

            if ($ticked) {
                $services[] = $field;
            }
        }

        $this->merge(['services' => $services]);

        if (! $this->boolean('service_other_support')) {
            $this->merge(['service_other_specify' => null]);
        }
    }
}
