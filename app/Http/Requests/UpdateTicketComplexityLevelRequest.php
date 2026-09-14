<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTicketComplexityLevelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('ticket_complexity_level')?->id;

        return [
            'label' => ['required', 'string', 'max:255', "unique:ticket_complexity_levels,label,{$id}"],
            'description' => ['nullable', 'string', 'max:1000'],
            'examples' => ['nullable', 'string', 'max:1000'],
            'min_minutes' => ['required', 'integer', 'min:0'],
            'max_minutes' => ['nullable', 'integer', 'gte:min_minutes'],
            'color' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
