<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'nik' => ['required', 'string', 'regex:/^[0-9]{8}$/', 'unique:employees,nik'],
            'nama' => ['required', 'string', 'max:255'],
            'jabatan' => ['required', 'string', 'max:255'],
            'level' => ['required', 'string', Rule::in(['Karpim', 'Karpel'])],
            'rm_level' => ['nullable', 'string', Rule::in(['RM-1', 'RM-2', 'RM-3', 'RM-4'])],
            'unit_kerja' => ['required', 'string', 'max:255'],
            'status_penugasan' => ['nullable', 'string', Rule::in(['Definitif', 'Diperbantukan', 'Pejabat Sementara (Pjs)', 'Tugas Belajar', 'Advisor'])],
            'status_karyawan' => ['required', 'string', 'max:50'],
            'tanggal_berakhir_kontrak' => ['nullable', 'date'],
            'golongan' => ['nullable', 'string', 'max:50'],
            'tanggal_dalam_jabatan' => ['nullable', 'date'],
            'tmt_unit_kerja' => ['nullable', 'date'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'jenis_kelamin' => ['nullable', 'string', Rule::in(['L', 'P', 'Laki-laki', 'Perempuan'])],
            'tanggal_lahir' => ['nullable', 'date'],
            'tmt_bekerja' => ['nullable', 'date'],
            'tanggal_diangkat_staf' => ['nullable', 'date'],
            'susunan_keluarga' => ['nullable', 'string', 'max:255'],
            'job_grade' => ['nullable', 'integer', 'between:1,16'],
            'person_grade' => ['nullable', 'integer', 'between:1,16'],
            'jalur_masuk' => ['nullable', 'string', Rule::in(['CKP', 'Talent Scouting', 'RBB', 'Reguler'])],
            'tanggal_mbt' => ['nullable', 'date'],
            'tanggal_pensiun' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
            'pendidikan_terakhir' => ['nullable', 'string', 'max:100'],
            'sekolah' => ['nullable', 'string', 'max:255'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'nomor_sk' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Custom validation messages in Indonesian.
     */
    public function messages(): array
    {
        return [
            'nik.required' => 'Nomor Induk Karyawan (NIK) wajib diisi.',
            'nik.regex' => 'Format NIK harus berupa 8 digit angka numerik (contoh: 13004521).',
            'nik.unique' => 'NIK ini sudah terdaftar dalam sistem.',
            'nama.required' => 'Nama lengkap pegawai wajib diisi.',
            'jabatan.required' => 'Jabatan formasi wajib diisi.',
            'level.required' => 'Strata level pegawai wajib dipilih.',
            'level.in' => 'Level strata harus bernilai Karpim atau Karpel.',
            'rm_level.in' => 'RM Band harus bernilai RM-1, RM-2, RM-3, atau RM-4.',
            'unit_kerja.required' => 'Unit kerja penempatan wajib dipilih.',
            'status_karyawan.required' => 'Status kepegawaian wajib dipilih.',
            'job_grade.between' => 'Job Grade harus berada dalam rentang 1 sampai 16.',
            'person_grade.between' => 'Person Grade harus berada dalam rentang 1 sampai 16.',
        ];
    }
}
