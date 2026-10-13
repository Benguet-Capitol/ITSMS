<?php

namespace App\Http\Requests;

use App\Models\DesignRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreDesignRequest extends FormRequest
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
            'date_needed' => ['required', 'date', 'after_or_equal:date_of_request'],

            'items' => ['required', 'array', 'min:1', 'max:'.count(DesignRequest::ITEMS)],
            'items.*' => ['array'],
            'items.*.item' => ['required', 'string', 'distinct', Rule::in(array_keys(DesignRequest::ITEMS))],
            'items.*.dimension' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'string', 'max:255'],
            'items.*.specify' => ['nullable', 'string', 'max:255', 'required_if:items.*.item,others'],

            'file_formats' => ['nullable', 'array'],
            'file_formats.*' => ['string', 'distinct', Rule::in(array_keys(DesignRequest::FILE_FORMATS))],
            'file_format_other' => ['nullable', 'string', 'max:100'],
            'media_provided' => ['nullable', 'array'],
            'media_provided.*' => ['string', 'distinct', Rule::in(array_keys(DesignRequest::MEDIA))],

            'requester_name' => ['required', 'string', 'max:255'],
            'requester_position' => ['nullable', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_needed.after_or_equal' => 'The date needed cannot be before the date of request.',
            'items.required' => 'Select at least one design/project.',
            'items.min' => 'Select at least one design/project.',
            'items.*.item.distinct' => 'Each design/project can only be listed once.',
            'items.*.specify.required_if' => 'Please specify the other design/project.',
        ];
    }

    /**
     * Validated data with the item list normalized: kept in the form's
     * order, blank details stored as null, and `specify` kept only for
     * "Others".
     *
     * @return array<string, mixed>
     */
    public function designAttributes(): array
    {
        $data = $this->validated();
        $order = array_flip(array_keys(DesignRequest::ITEMS));
        $blankToNull = fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $data['items'] = collect($data['items'])
            ->map(fn (array $item) => [
                'item' => $item['item'],
                'dimension' => $blankToNull($item['dimension'] ?? null),
                'quantity' => $blankToNull($item['quantity'] ?? null),
                'specify' => $item['item'] === 'others' ? $blankToNull($item['specify'] ?? null) : null,
            ])
            ->sortBy(fn (array $item) => $order[$item['item']])
            ->values()
            ->all();

        $data['file_formats'] = array_values($data['file_formats'] ?? []);
        $data['media_provided'] = array_values($data['media_provided'] ?? []);

        return $data;
    }
}
