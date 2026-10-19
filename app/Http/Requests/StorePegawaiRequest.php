<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePegawaiRequest extends FormRequest
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
        return [
            'email' => [
                'nullable',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $exists = User::query()
                        ->whereNull('deleted_at')
                        ->whereRaw('lower(email) = lower(?)', [$value])
                        ->exists();

                    if ($exists) {
                        $fail('The :attribute has already been taken.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8'],
            'nip' => [
                'required',
                'string',
                'max:30',
                Rule::unique('pegawai', 'nip')->whereNull('deleted_at'),
                Rule::unique('users', 'username')->whereNull('deleted_at'),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'bidang' => ['nullable', 'string', 'max:255'],
            'is_male' => ['nullable', 'boolean'],
            'tmt' => ['nullable', 'date'],
        ];
    }
}
