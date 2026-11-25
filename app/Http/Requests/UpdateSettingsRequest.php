<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'active_api_key_id' => [
                'nullable',
                'integer',
                Rule::exists('api_keys', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }
}
