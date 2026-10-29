<?php

namespace App\Http\Requests;

use App\Models\Inspector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Create and update share these rules; the unique name ignores the inspector being edited. */
class StoreInspectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('inspectors', 'name')->ignore($this->route('inspector')),
            ],
            'position' => ['nullable', 'string', 'max:255'],
            'office' => ['required', 'string', 'max:255'],
            ...Inspector::officeRules(),
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'This inspector is already on the list.',
            'office.required' => 'Select the office.',
        ];
    }

    /** Trim the text fields; blank becomes null. */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(['name', 'position', 'office'])
            ->filter(fn (string $key) => is_string($this->input($key)))
            ->mapWithKeys(fn (string $key) => [$key => trim($this->input($key)) ?: null])
            ->all());
    }
}
