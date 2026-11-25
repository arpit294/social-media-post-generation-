<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateManufacturingPostRequest extends FormRequest
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
            'platform' => ['required', 'in:LinkedIn,Instagram,Facebook'],
            'language' => ['required', 'in:English,Gujarati-mix'],
            'prompt' => ['required', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt.required' => 'Enter a prompt before writing the post.',
        ];
    }
}
