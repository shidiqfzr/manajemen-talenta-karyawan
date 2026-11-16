<?php

namespace App\Http\Requests\Admin;

use App\Models\Training;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi tunggal untuk simpan dan ubah data Pelatihan (DOMAIN.md §2.4 dan §7).
 * Kolom biaya dan jam belajar bertipe integer 32-bit pada basis data.
 */
class TrainingRequest extends FormRequest
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
        // Pada perubahan, nilai lama yang tersimpan sebelum daftar baku berlaku tetap boleh dipertahankan.
        $current = $this->route('training');
        $current = $current instanceof Training ? $current : null;

        return [
            'judul' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'string', Rule::in(Training::jenisOptions($current?->jenis))],
            'metode' => ['required', 'string', Rule::in(Training::metodeOptions($current?->metode))],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_akhir' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'biaya_tiket_peserta' => ['required', 'integer', 'min:0', 'max:'.Training::BIAYA_MAX],
            'biaya_pelatihan' => ['required', 'integer', 'min:0', 'max:'.Training::BIAYA_MAX],
            'penyelenggara' => ['required', 'string', 'max:255'],
            'nomor_surat' => ['required', 'string', 'max:255'],
            'tanggal_surat' => ['required', 'date'],
            'keterangan' => ['nullable', 'string', 'max:5000'],
            'bidang_pelatihan' => ['required', 'string', Rule::in(array_keys(Training::bidangOptions($current?->bidang_pelatihan)))],
            'jam_belajar_per_hari' => ['required', 'integer', 'between:1,'.Training::JAM_BELAJAR_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'judul' => 'Judul Pelatihan',
            'jenis' => 'Jenis Pelatihan',
            'metode' => 'Metode',
            'tanggal_mulai' => 'Tanggal Mulai',
            'tanggal_akhir' => 'Tanggal Akhir',
            'biaya_tiket_peserta' => 'Biaya Tiket Peserta',
            'biaya_pelatihan' => 'Biaya Pelatihan',
            'penyelenggara' => 'Penyelenggara',
            'nomor_surat' => 'Nomor Surat',
            'tanggal_surat' => 'Tanggal Surat',
            'keterangan' => 'Keterangan',
            'bidang_pelatihan' => 'Bidang Pelatihan',
            'jam_belajar_per_hari' => 'Jam Belajar per Hari',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'jenis.in' => 'Jenis Pelatihan harus dipilih dari daftar yang tersedia.',
            'metode.in' => 'Metode harus dipilih dari daftar yang tersedia.',
            'bidang_pelatihan.in' => 'Bidang Pelatihan harus dipilih dari daftar yang tersedia.',
            'tanggal_akhir.after_or_equal' => 'Tanggal Akhir tidak boleh lebih awal dari Tanggal Mulai.',
            'min' => [
                'numeric' => ':attribute tidak boleh bernilai negatif.',
            ],
            'max' => [
                'numeric' => ':attribute melebihi batas yang diizinkan.',
                'string' => ':attribute tidak boleh lebih dari :max karakter.',
            ],
            'between' => [
                'numeric' => ':attribute harus berada pada rentang :min sampai :max.',
            ],
        ];
    }
}
