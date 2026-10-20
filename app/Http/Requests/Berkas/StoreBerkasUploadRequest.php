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
            'size' => [
                'required',
                'integer',
                'min:1',
                'max:'.$this->maxBytes(),
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
            'size.required' => 'The file size is required.',
            'size.integer' => 'The file size must be an integer number of bytes.',
            'size.min' => 'The file may not be empty.',
            'size.max' => 'The file may not be greater than '.$this->maxMegabytes().' MB.',
        ];
    }

    private function maxBytes(): int
    {
        return (int) config('filesystems.disks.s3-temp.max_bytes', 10 * 1024 * 1024);
    }

    private function maxMegabytes(): int|float
    {
        $megabytes = $this->maxBytes() / (1024 * 1024);

        return fmod($megabytes, 1.0) === 0.0 ? (int) $megabytes : round($megabytes, 2);
    }
}
