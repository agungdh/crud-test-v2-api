<?php

namespace App\Http\Requests;

use App\Models\Pegawai;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePegawaiRequest extends FormRequest
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
        /** @var Pegawai $pegawai */
        $pegawai = $this->route('pegawai');

        return [
            'email' => [
                'present',
                'nullable',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($pegawai): void {
                    if ($value === null) {
                        return;
                    }

                    $exists = User::query()
                        ->whereRaw('lower(email) = lower(?)', [$value])
                        ->whereKeyNot($pegawai->user_id)
                        ->exists();

                    if ($exists) {
                        $fail('The :attribute has already been taken.');
                    }
                },
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'nip' => [
                'required',
                'string',
                'max:30',
                Rule::unique('pegawai', 'nip')->whereNull('deleted_at')->ignore($pegawai),
                Rule::unique('users', 'username')->whereNull('deleted_at')->ignore($pegawai->user_id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['present', 'nullable', 'date'],
            'jabatan' => ['present', 'nullable', 'string', 'max:255'],
            'bidang' => ['present', 'nullable', 'string', 'max:255'],
            'is_male' => ['present', 'nullable', 'boolean'],
            'tmt' => ['present', 'nullable', 'date'],
        ];
    }
}
