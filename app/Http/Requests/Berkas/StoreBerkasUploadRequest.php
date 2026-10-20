<?php

namespace App\Http\Requests\Berkas;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBerkasUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filename' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\x00-\x1F\/\\\\]+\.(pdf|jpe?g|png)$/i',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'filename.regex' => 'The filename must end with pdf, jpg, jpeg, or png.',
        ];
    }
}
