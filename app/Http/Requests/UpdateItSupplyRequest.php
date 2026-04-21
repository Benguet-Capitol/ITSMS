<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateItSupplyRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'brand_model_id' => 'required|exists:brand_models,id',
            'measurement_unit_id' => 'required|exists:measurement_units,id',
            'description' => 'nullable|string',
            'item_number' => 'nullable|string',
            'stock_number' => 'nullable|string',
            'ics_number' => ['nullable', 'string', 'max:255'],
            'iar_number' => ['nullable', 'string', 'max:255'],
            'po_number' => ['nullable', 'string', 'max:255'],
            'quantity' => 'required|numeric',
        ];
    }
}
