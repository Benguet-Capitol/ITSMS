<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateUserRequest extends FormRequest
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
          'username' => ['required', 'string', 'max:255'],
          'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $this->user->id],
          // 'password' => ['required', 'string', 'min:8', 'max:255'],
          'photo_id' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
          'role' => ['required', 'integer', 'exists:roles,id'],
          'display_name' => ['required', 'string', 'max:255'],
          'name' => ['required', 'string', 'max:255'],
          'gender' => ['required', 'string', 'max:255'],
          'designation' => ['required', 'string', 'max:255'],

          'offices_assigned_ids' => ['nullable', 'array'],
          'offices_assigned_ids.*' => ['integer', 'exists:departments,id'],
          
          'agencies_assigned_ids' => ['nullable', 'array'],
          'agencies_assigned_ids.*' => ['integer', 'exists:agencies,id'],
        ];
    }
}
