<?php

namespace App\Http\Requests;

use App\Models\DocumentationRequest;
use App\Support\DateSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreDocumentationRequest extends FormRequest
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

            'service_photo' => ['boolean'],
            'service_video' => ['boolean'],
            'service_live_feed' => ['boolean'],
            'services' => ['array', 'min:1'],

            'program_activity_details' => ['required', 'string', 'max:5000'],
            ...DateSchedule::rules(),
            'specific_shots' => ['nullable', 'string', 'max:5000'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            ...DateSchedule::messages('schedule', 'date of documentation'),
            'services.min' => 'Select at least one service: photo, video, or live feed.',
        ];
    }

    /**
     * Normalizes the three service checkboxes (absent = unticked) and
     * derives `services`, the list of ticked ones, so "pick at least one"
     * is a plain rule with its own error key for the form to show under the
     * Service Requested group. `services` isn't fillable, so it's never
     * saved.
     */
    protected function prepareForValidation(): void
    {
        $services = [];

        foreach (array_keys(DocumentationRequest::SERVICES) as $field) {
            $ticked = $this->boolean($field);
            $this->merge([$field => $ticked]);

            if ($ticked) {
                $services[] = $field;
            }
        }

        $this->merge(['services' => $services]);

        if ($this->has('schedule')) {
            $this->merge(['schedule' => DateSchedule::normalize($this->input('schedule'))]);
        }
    }
}
