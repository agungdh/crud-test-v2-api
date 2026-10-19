<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiklatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->pegawai()->exists() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The attachment is keep-or-replace: an omitted key keeps the stored file
     * and an explicit null removes it, because multipart clients cannot send
     * an empty file part reliably.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'jenis_pelatihan_uuid' => [
                'required',
                'uuid',
                Rule::exists('jenis_pelatihan', 'uuid')->whereNull('deleted_at'),
            ],
            'nomor_surat' => ['required', 'string', 'max:255'],
            'materi_pengembangan' => ['required', 'string', 'max:255'],
            'dari_tanggal_pelaksanaan' => ['required', 'date', 'before_or_equal:sampai_tanggal_pelaksanaan'],
            'sampai_tanggal_pelaksanaan' => ['required', 'date', 'after_or_equal:dari_tanggal_pelaksanaan'],
            'jumlah_jam_pelatihan' => ['required', 'integer', 'min:1'],
            'berkas' => ['sometimes', 'nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ];
    }
}
