<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateChatPostRequest extends FormRequest
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
        ];
    }
}
