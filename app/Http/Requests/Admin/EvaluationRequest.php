<?php

namespace App\Http\Requests\Admin;

use App\Models\Evaluation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi tunggal untuk simpan dan ubah Penilaian Karyawan (DOMAIN.md §2.3 dan §6).
 * Rentang nilai mengikuti isian pada formulir; kolom desimal basis data adalah (5,2).
 */
class EvaluationRequest extends FormRequest
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
        // Pada perubahan, kategori lama yang tersimpan sebelum daftar baku berlaku tetap boleh dipertahankan.
        $current = $this->route('evaluation');
        $current = $current instanceof Evaluation ? $current : null;

        $rules = [
            'nilai_kepemimpinan' => ['nullable', 'numeric', 'between:0,'.Evaluation::NILAI_MAX],
            'nilai_perilaku_budaya' => ['nullable', 'numeric', 'between:0,'.Evaluation::NILAI_MAX],
            'nilai_pengalaman_teknis' => ['nullable', 'numeric', 'between:0,'.Evaluation::NILAI_MAX],
            'nilai_kematangan_pribadi' => ['nullable', 'numeric', 'between:0,'.Evaluation::NILAI_MAX],
            'skor_smkbk_9box' => ['nullable', 'numeric', 'between:0,'.Evaluation::SKOR_MAX],
            'skor_cli_9box' => ['nullable', 'numeric', 'between:0,'.Evaluation::SKOR_MAX],
            'kategori_9box' => ['nullable', 'string', Rule::in(array_keys(Evaluation::boxOptions($current?->kategori_9box)))],
            'bidang_tugas' => ['nullable', 'string', 'max:255'],
            'lembaga_asesmen' => ['nullable', 'string', 'max:255'],
            'tanggal_pelaksanaan_asesmen' => ['nullable', 'date'],
            'hasil_skor_asesmen' => ['nullable', 'numeric', 'between:0,'.Evaluation::NILAI_MAX],
            'kategori_asesmen' => ['nullable', 'string', 'max:255'],
            'keterangan_asesmen' => ['nullable', 'string', 'max:255'],
            'expired_asesmen' => ['nullable', 'date', 'after_or_equal:tanggal_pelaksanaan_asesmen'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
        ];

        // Karyawan hanya dipilih saat penilaian dibuat; pada perubahan, pemilik penilaian tidak boleh diganti.
        if ($this->isMethod('POST')) {
            $rules['employee_nik'] = ['required', 'string', 'regex:/^\d{8}$/', 'exists:employees,nik'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_nik' => 'Nomor Induk Karyawan',
            'nilai_kepemimpinan' => 'Nilai Kepemimpinan',
            'nilai_perilaku_budaya' => 'Nilai Perilaku Budaya',
            'nilai_pengalaman_teknis' => 'Nilai Pengalaman Teknis',
            'nilai_kematangan_pribadi' => 'Nilai Kematangan Pribadi',
            'skor_smkbk_9box' => 'Skor SMK-BK',
            'skor_cli_9box' => 'Skor CLI',
            'kategori_9box' => 'Kategori 9-Box',
            'bidang_tugas' => 'Bidang Tugas',
            'lembaga_asesmen' => 'Lembaga Asesmen',
            'tanggal_pelaksanaan_asesmen' => 'Tanggal Pelaksanaan Asesmen',
            'hasil_skor_asesmen' => 'Hasil Skor Asesmen',
            'kategori_asesmen' => 'Kategori Asesmen',
            'keterangan_asesmen' => 'Keterangan Asesmen',
            'expired_asesmen' => 'Masa Berlaku Asesmen',
            'keterangan' => 'Keterangan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_nik.required' => 'Karyawan yang dinilai wajib dipilih.',
            'employee_nik.regex' => 'Nomor Induk Karyawan harus terdiri dari 8 digit angka.',
            'employee_nik.exists' => 'Karyawan yang dipilih tidak terdaftar.',
            'kategori_9box.in' => 'Kategori 9-Box harus dipilih dari sembilan kuadran yang tersedia.',
            'numeric' => ':attribute harus berupa angka.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'expired_asesmen.after_or_equal' => 'Masa Berlaku Asesmen tidak boleh lebih awal dari Tanggal Pelaksanaan Asesmen.',
            'between' => [
                'numeric' => ':attribute harus berada pada rentang :min sampai :max.',
            ],
            'max' => [
                'string' => ':attribute tidak boleh lebih dari :max karakter.',
            ],
        ];
    }
}
