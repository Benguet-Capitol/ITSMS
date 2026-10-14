<?php

namespace App\Http\Requests;

use App\Models\CctvFootageRequest;
use App\Support\DateSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCctvFootageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Control number and status are server-assigned on create, so neither
     * is accepted here.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'date_of_request' => ['required', 'date'],
            'requester_name' => ['required', 'string', 'max:255'],
            'requester_address' => ['nullable', 'string', 'max:255'],
            'requester_contact_number' => ['nullable', 'string', 'max:50'],

            // Incident date(s): single days and/or ranges, none after the request date.
            // One footage window for all dates, or a window per date when they differ.
            ...DateSchedule::rules('schedule', required: true, notAfterField: 'date_of_request', time: DateSchedule::TIME_RANGE),
            'footage_time_from' => ['nullable', 'date_format:H:i', DateSchedule::sharedTimeRule($this->input('schedule'))],
            'footage_time_to' => ['nullable', 'date_format:H:i', 'required_with:footage_time_from', DateSchedule::sharedTimeRule($this->input('schedule'))],
            'camera_location' => ['required', 'string', 'max:255'],

            'requester_capacity' => ['required', 'string', Rule::in(CctvFootageRequest::CAPACITIES)],
            'requester_capacity_other' => ['nullable', 'string', 'max:255', 'required_if:requester_capacity,other'],

            'purpose' => ['required', 'string', Rule::in(CctvFootageRequest::PURPOSES)],
            'reasons' => ['required', 'array', 'min:1'],
            'reasons.*' => ['string', 'distinct', Rule::in(CctvFootageRequest::REASONS)],
            'reason_others' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => in_array('others', (array) $this->input('reasons', []), true)),
            ],
            'description' => ['nullable', 'string', 'max:5000'],

            'documents_presented' => ['nullable', 'array'],
            'documents_presented.*' => ['string', 'distinct', Rule::in(CctvFootageRequest::DOCUMENTS)],
        ];
    }

    public function messages(): array
    {
        return [
            ...DateSchedule::messages('schedule', 'date of incident'),
            'schedule.*.from.before_or_equal' => 'The date of incident cannot be after the date of request.',
            'schedule.*.to.before_or_equal' => 'The date of incident cannot be after the date of request.',
            'requester_capacity_other.required_if' => 'Please specify the other legitimate interest.',
            'reason_others.required' => 'Please specify the other reason.',
            'footage_time_to.required_with' => 'Please provide the end of the inclusive time.',
            'footage_time_from.prohibited' => DateSchedule::SHARED_TIME_MESSAGE,
            'footage_time_to.prohibited' => DateSchedule::SHARED_TIME_MESSAGE,
            'schedule.*.time_to.required_with' => 'Please provide the end of the inclusive time.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(DateSchedule::prepare($this->all(), ['time' => 'footage_time_from', 'time_to' => 'footage_time_to']));
    }

    /**
     * Validated data with the "please specify" fields cleared when their
     * option isn't selected, so a stale value doesn't linger after the
     * user unticks "Other".
     *
     * @return array<string, mixed>
     */
    public function cctvAttributes(): array
    {
        $data = $this->validated();

        if (($data['requester_capacity'] ?? null) !== 'other') {
            $data['requester_capacity_other'] = null;
        }

        if (! in_array('others', $data['reasons'] ?? [], true)) {
            $data['reason_others'] = null;
        }

        $data['documents_presented'] = array_values($data['documents_presented'] ?? []);

        return $data;
    }
}
