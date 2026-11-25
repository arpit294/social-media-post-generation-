<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'api_key' => ['required', 'string', 'max:300'],
            'model' => ['nullable', 'string', 'max:100'],
        ];
    }
}
