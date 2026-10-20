<?php

namespace App\Http\Requests\JenisPelatihan;

use App\Models\JenisPelatihan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJenisPelatihanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var JenisPelatihan $jenisPelatihan */
        $jenisPelatihan = $this->route('jenis_pelatihan');

        return [
            'jenis_pelatihan' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($jenisPelatihan): void {
                    $exists = JenisPelatihan::query()
                        ->whereRaw('lower(jenis_pelatihan) = lower(?)', [(string) $value])
                        ->whereKeyNot($jenisPelatihan->id)
                        ->exists();

                    if ($exists) {
                        $fail('The :attribute has already been taken.');
                    }
                },
            ],
        ];
    }
}
