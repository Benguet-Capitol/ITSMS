<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\ServiceMethod;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Support\Facades\Auth;

class ResolveTicketRequest extends FormRequest
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
            'service_method' => ['nullable', new Enum(ServiceMethod::class)],
            'solution_id' => 'required|exists:solutions,id',
        ];
    }
}
