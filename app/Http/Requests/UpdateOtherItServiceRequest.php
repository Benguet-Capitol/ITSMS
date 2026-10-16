<?php

namespace App\Http\Requests;

use App\Models\OtherItServiceRequest;
use App\Support\DateSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateOtherItServiceRequest extends FormRequest
{
    /**
     * "Others: (Please Specify)" belongs to the standalone Other service
     * checkbox, so unticking it drops any leftover specify text.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->boolean('service_other_service')) {
            $this->merge(['service_others_specify' => null]);
        }

        $this->merge(DateSchedule::prepare($this->all(), ['time' => 'activity_time']));
    }

    public function messages(): array
    {
        return [
            ...OtherItServiceRequest::officeMessages(),
            ...DateSchedule::messages('schedule', 'date of activity'),
            'activity_time.prohibited' => DateSchedule::SHARED_TIME_MESSAGE,
        ];
    }

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
        return [
            'date_of_request' => ['nullable', 'date'],
            'department_office' => ['nullable', 'string', 'max:255'],
            ...OtherItServiceRequest::officeRules(),
            'requestor_name' => ['nullable', 'string', 'max:255'],

            'service_printing' => ['nullable', 'boolean'],
            'service_information_material' => ['nullable', 'boolean'],
            'service_program_paper' => ['nullable', 'boolean'],
            'service_brochure' => ['nullable', 'boolean'],
            'service_iec_material' => ['nullable', 'boolean'],
            'service_handbook' => ['nullable', 'boolean'],
            'service_certificates' => ['nullable', 'boolean'],
            'service_others' => ['nullable', 'boolean'],
            'service_qty' => ['nullable', 'integer', 'min:1'],
            'service_laptop_tv_setup' => ['nullable', 'boolean'],
            'service_ledwall_eoc' => ['nullable', 'boolean'],
            'service_other_service' => ['nullable', 'boolean'],
            'service_others_specify' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn () => $this->boolean('service_other_service')),
            ],

            'program_activity_details' => ['nullable', 'string'],
            // activity_date_text is written from the schedule by the model.
            // One time for all dates, or a time per date when they differ.
            ...DateSchedule::rules('schedule', required: false, time: DateSchedule::TIME_TEXT),
            'activity_time' => ['nullable', 'string', 'max:50', DateSchedule::sharedTimeRule($this->input('schedule'))],
            'activity_venue' => ['nullable', 'string', 'max:255'],

            'action_taken' => ['nullable', 'string'],

            'feedback_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'feedback_date' => ['nullable', 'date'],
        ];
    }
}
