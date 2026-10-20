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
            'max_size' => [
                'required',
                'integer',
                'min:1',
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
            'max_size.required' => 'The maximum file size is required.',
            'max_size.integer' => 'The maximum file size must be an integer number of bytes.',
            'max_size.min' => 'The maximum file size must be at least 1 byte.',
        ];
    }
}
