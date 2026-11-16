<?php

namespace App\Http\Requests\Admin;

use App\Services\EmployeeDeletionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Alasan wajib untuk penghapusan data (SECURITY.md butir 6a): 10–500 karakter, aturan yang sama dengan
 * penghapusan karyawan. Dipakai oleh penghapusan Penilaian dan Pelatihan.
 *
 * Penghapusan dikirim dari modal konfirmasi pada halaman daftar yang tidak menampilkan galat per kolom,
 * sehingga kegagalan dikembalikan sebagai pesan gagal (`x-admin.flash`) pada halaman yang sama.
 */
class DeletionReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['alasan' => EmployeeDeletionService::REASON_RULES];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $min = EmployeeDeletionService::REASON_MIN_LENGTH;
        $max = EmployeeDeletionService::REASON_MAX_LENGTH;

        return [
            'alasan.required' => "Alasan penghapusan wajib diisi (minimal {$min} karakter).",
            'alasan.min' => "Alasan penghapusan minimal {$min} karakter.",
            'alasan.max' => "Alasan penghapusan maksimal {$max} karakter.",
            'alasan.string' => 'Alasan penghapusan harus berupa teks.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()->back()->with('error', $validator->errors()->first('alasan'))
        );
    }
}
